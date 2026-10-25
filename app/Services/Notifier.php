<?php

declare(strict_types=1);

namespace Sesam\Services;

use Sesam\Core\Database\Connection;
use Sesam\Core\Logger\Logger;
use Sesam\Core\Push\FcmClient;
use Sesam\Core\Push\NotificationCatalog;
use Sesam\Core\Push\PushException;

/**
 * Envoi des notifications : une ligne dans la cloche de chaque destinataire, puis un push Firebase
 * sur chacun de ses appareils, selon ses préférences. Utilisé par tous les modules et la console SESAM.
 *
 * Les services l'obtiennent par Notifier::current() (posé par le Bootstrap) : un échec d'envoi
 * n'empêche jamais l'action métier (commande, réservation, paiement) d'aboutir.
 */
final class Notifier
{
    private static ?self $current = null;

    private readonly \PDO $pdo;
    private ?FcmClient $fcm = null;

    /** @var list<array{token: string, data: array<string, string>}> Envois journalisés (driver « log ») : lus par les tests. */
    private array $sent = [];

    /**
     * @param array{host: string, port: string, name: string, user: string, password: string} $database
     * @param array<string, mixed> $config config/firebase.php
     */
    public function __construct(
        array $database,
        private readonly array $config,
        private readonly ?Logger $logger = null,
    ) {
        $this->pdo = Connection::make($database);
        if (($config['driver'] ?? 'log') === 'fcm') {
            $this->fcm = new FcmClient(
                (string) ($config['credentials'] ?? ''),
                (string) ($config['project_id'] ?? ''),
                (string) ($config['cache_dir'] ?? sys_get_temp_dir()),
                (string) ($config['ca_bundle'] ?? ''),
            );
        }
    }

    public static function setCurrent(?self $notifier): void
    {
        self::$current = $notifier;
    }

    public static function current(): ?self
    {
        return self::$current;
    }

    // ================================================================ Destinataires

    /**
     * Comptes actifs d'un commerce qui ont la permission demandée (vide : tous).
     *
     * @return list<int>
     */
    public function tenantUsers(int $tenantId, string $permission = ''): array
    {
        if ($permission === '') {
            $statement = $this->pdo->prepare('SELECT id FROM users WHERE tenant_id = ? AND is_active = 1');
            $statement->execute([$tenantId]);
        } else {
            $statement = $this->pdo->prepare(
                'SELECT DISTINCT u.id FROM users u
                 JOIN user_roles ur ON ur.user_id = u.id
                 JOIN role_permissions rp ON rp.role_id = ur.role_id
                 JOIN permissions p ON p.id = rp.permission_id
                 WHERE u.tenant_id = ? AND u.is_active = 1 AND p.slug = ?'
            );
            $statement->execute([$tenantId, $permission]);
        }

        return array_map('intval', $statement->fetchAll(\PDO::FETCH_COLUMN));
    }

    /**
     * Personnel SESAM actif qui a la permission demandée.
     *
     * @return list<int>
     */
    public function staffUsers(string $permission): array
    {
        $statement = $this->pdo->prepare(
            'SELECT DISTINCT u.id FROM users u
             JOIN user_roles ur ON ur.user_id = u.id
             JOIN role_permissions rp ON rp.role_id = ur.role_id
             JOIN permissions p ON p.id = rp.permission_id
             WHERE u.tenant_id IS NULL AND u.is_active = 1 AND p.slug = ?'
        );
        $statement->execute([$permission]);

        return array_map('intval', $statement->fetchAll(\PDO::FETCH_COLUMN));
    }

    // ================================================================ Envoi

    /**
     * Notifie les comptes d'un commerce concernés par la catégorie (permission du catalogue).
     */
    public function toTenant(int $tenantId, string $category, string $title, string $body, ?string $link = null, string $level = 'info', ?string $onceKey = null, ?int $exceptUserId = null): int
    {
        $permission = NotificationCatalog::CATEGORIES[$category]['permission'] ?? '';
        $users = array_values(array_filter($this->tenantUsers($tenantId, $permission), static fn (int $id): bool => $id !== $exceptUserId));

        return $this->send($users, $tenantId, $category, $title, $body, $link, $level, $onceKey);
    }

    /**
     * Comme toTenant, sans jamais faire échouer l'action en cours (appelé depuis les services métier).
     */
    public static function tenant(int $tenantId, string $category, string $title, string $body, ?string $link = null, string $level = 'info', ?string $onceKey = null, ?int $exceptUserId = null): void
    {
        try {
            self::$current?->toTenant($tenantId, $category, $title, $body, $link, $level, $onceKey, $exceptUserId);
        } catch (\Throwable $exception) {
            self::$current?->logger?->error('notification ' . $category . ' : ' . $exception->getMessage());
        }
    }

    /**
     * Comme toStaff, sans jamais faire échouer l'action en cours.
     */
    public static function staff(string $category, string $title, string $body, ?string $link = null, string $level = 'info', ?string $onceKey = null, ?int $exceptUserId = null): void
    {
        try {
            self::$current?->toStaff($category, $title, $body, $link, $level, $onceKey, $exceptUserId);
        } catch (\Throwable $exception) {
            self::$current?->logger?->error('notification ' . $category . ' : ' . $exception->getMessage());
        }
    }

    /**
     * Notifie le personnel SESAM concerné par la catégorie.
     */
    public function toStaff(string $category, string $title, string $body, ?string $link = null, string $level = 'info', ?string $onceKey = null, ?int $exceptUserId = null): int
    {
        $permission = NotificationCatalog::CATEGORIES[$category]['permission'] ?? '';
        if ($permission === '') {
            return 0;
        }
        $users = array_values(array_filter($this->staffUsers($permission), static fn (int $id): bool => $id !== $exceptUserId));

        return $this->send($users, null, $category, $title, $body, $link, $level, $onceKey);
    }

    /**
     * Envoi à une liste de comptes. $onceKey : la notification n'est envoyée qu'une seule fois (rappels planifiés).
     * Renvoie le nombre de comptes notifiés.
     *
     * @param list<int> $userIds
     */
    public function send(array $userIds, ?int $tenantId, string $category, string $title, string $body, ?string $link = null, string $level = 'info', ?string $onceKey = null): int
    {
        $userIds = array_values(array_unique(array_filter($userIds, static fn (int $id): bool => $id > 0)));
        if ($userIds === []) {
            return 0;
        }
        if ($onceKey !== null && !$this->claim($onceKey)) {
            return 0;
        }
        $title = mb_substr(trim($title), 0, 120);
        $body = mb_substr(trim($body), 0, 500);
        $level = in_array($level, ['info', 'success', 'warning', 'danger'], true) ? $level : 'info';
        $prefs = $this->preferences($userIds, $category);
        $notified = 0;
        foreach ($userIds as $userId) {
            $pref = $prefs[$userId] ?? ['in_app' => 1, 'push' => 1];
            if ((int) $pref['in_app'] !== 1 && (int) $pref['push'] !== 1) {
                continue;
            }
            $this->pdo->prepare(
                'INSERT INTO notifications (tenant_id, user_id, category, level, title, body, link, push_status, read_at, created_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, NULL, ?, UTC_TIMESTAMP())'
            )->execute([$tenantId, $userId, $category, $level, $title, $body, $link, (int) $pref['in_app'] === 1 ? null : gmdate('Y-m-d H:i:s')]);
            $notificationId = (int) $this->pdo->lastInsertId();
            $status = (int) $pref['push'] === 1 ? $this->pushToUser($userId, $notificationId, $category, $title, $body, $link, $level) : 'off';
            $this->pdo->prepare('UPDATE notifications SET push_status = ? WHERE id = ?')->execute([$status, $notificationId]);
            $notified++;
        }

        return $notified;
    }

    /**
     * Notification de test vers les appareils d'un compte (bouton « Envoyer un test » du profil).
     *
     * @return array{devices: int, sent: int, errors: list<string>}
     */
    public function test(int $userId, ?int $tenantId): array
    {
        $before = $this->deviceCount($userId);
        $this->send([$userId], $tenantId, 'general', 'Notifications activées ✓', 'Vous recevrez ici les alertes de votre espace SESAM.', '/notifications', 'success');
        $errors = $this->pdo->prepare('SELECT last_error FROM push_devices WHERE user_id = ? AND last_error IS NOT NULL');
        $errors->execute([$userId]);
        $failed = array_values(array_filter(array_map('strval', $errors->fetchAll(\PDO::FETCH_COLUMN))));

        return ['devices' => $before, 'sent' => max(0, $this->deviceCount($userId) - count($failed)), 'errors' => $failed];
    }

    /**
     * Pushs journalisés en mode « log » (tests).
     *
     * @return list<array{token: string, data: array<string, string>}>
     */
    public function sent(): array
    {
        return $this->sent;
    }

    public function isLive(): bool
    {
        return $this->fcm !== null;
    }

    // ================================================================ Appareils

    public function registerDevice(int $userId, ?int $tenantId, string $token, string $label): void
    {
        $token = trim($token);
        if ($token === '' || strlen($token) > 512 || preg_match('/^[A-Za-z0-9_:\-\.]+$/', $token) !== 1) {
            throw new \InvalidArgumentException('Jeton d\'appareil invalide.');
        }
        $this->pdo->prepare(
            'INSERT INTO push_devices (user_id, tenant_id, token, token_hash, label, created_at, last_seen_at)
             VALUES (?, ?, ?, ?, ?, UTC_TIMESTAMP(), UTC_TIMESTAMP())
             ON DUPLICATE KEY UPDATE user_id = VALUES(user_id), tenant_id = VALUES(tenant_id), label = VALUES(label), last_seen_at = UTC_TIMESTAMP(), last_error = NULL'
        )->execute([$userId, $tenantId, $token, hash('sha256', $token), mb_substr($label, 0, 120)]);
    }

    public function forgetDevice(int $userId, int $deviceId): void
    {
        $this->pdo->prepare('DELETE FROM push_devices WHERE id = ? AND user_id = ?')->execute([$deviceId, $userId]);
    }

    public function forgetToken(int $userId, string $token): void
    {
        $this->pdo->prepare('DELETE FROM push_devices WHERE token_hash = ? AND user_id = ?')->execute([hash('sha256', trim($token)), $userId]);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function devices(int $userId): array
    {
        $statement = $this->pdo->prepare('SELECT id, label, created_at, last_seen_at, last_sent_at, last_error FROM push_devices WHERE user_id = ? ORDER BY last_seen_at DESC');
        $statement->execute([$userId]);

        return $statement->fetchAll(\PDO::FETCH_ASSOC);
    }

    private function deviceCount(int $userId): int
    {
        $statement = $this->pdo->prepare('SELECT COUNT(*) FROM push_devices WHERE user_id = ?');
        $statement->execute([$userId]);

        return (int) $statement->fetchColumn();
    }

    // ================================================================ Préférences

    /**
     * @return array<string, array{in_app: int, push: int}>
     */
    public function preferencesOf(int $userId): array
    {
        $statement = $this->pdo->prepare('SELECT category, in_app, push FROM notification_preferences WHERE user_id = ?');
        $statement->execute([$userId]);
        $prefs = [];
        foreach ($statement->fetchAll(\PDO::FETCH_ASSOC) as $row) {
            $prefs[(string) $row['category']] = ['in_app' => (int) $row['in_app'], 'push' => (int) $row['push']];
        }

        return $prefs;
    }

    /**
     * @param array<string, array{in_app: bool, push: bool}> $choices
     */
    public function savePreferences(int $userId, array $choices): void
    {
        $statement = $this->pdo->prepare(
            'INSERT INTO notification_preferences (user_id, category, in_app, push, updated_at) VALUES (?, ?, ?, ?, UTC_TIMESTAMP())
             ON DUPLICATE KEY UPDATE in_app = VALUES(in_app), push = VALUES(push), updated_at = UTC_TIMESTAMP()'
        );
        foreach ($choices as $category => $choice) {
            if (!isset(NotificationCatalog::CATEGORIES[$category])) {
                continue;
            }
            $statement->execute([$userId, $category, $choice['in_app'] ? 1 : 0, $choice['push'] ? 1 : 0]);
        }
    }

    /**
     * @param list<int> $userIds
     * @return array<int, array{in_app: int, push: int}>
     */
    private function preferences(array $userIds, string $category): array
    {
        $statement = $this->pdo->prepare(
            'SELECT user_id, in_app, push FROM notification_preferences WHERE category = ? AND user_id IN (' . implode(',', array_fill(0, count($userIds), '?')) . ')'
        );
        $statement->execute(array_merge([$category], $userIds));
        $prefs = [];
        foreach ($statement->fetchAll(\PDO::FETCH_ASSOC) as $row) {
            $prefs[(int) $row['user_id']] = ['in_app' => (int) $row['in_app'], 'push' => (int) $row['push']];
        }

        return $prefs;
    }

    // ================================================================ Push

    private function pushToUser(int $userId, int $notificationId, string $category, string $title, string $body, ?string $link, string $level): string
    {
        $statement = $this->pdo->prepare('SELECT id, token FROM push_devices WHERE user_id = ?');
        $statement->execute([$userId]);
        $devices = $statement->fetchAll(\PDO::FETCH_ASSOC);
        if ($devices === []) {
            return 'no-device';
        }
        $data = [
            'title' => $title,
            'body' => $body,
            'link' => $link ?? '/notifications',
            'category' => $category,
            'level' => $level,
            'tag' => $category . '-' . $notificationId,
            'id' => (string) $notificationId,
        ];
        $delivered = 0;
        foreach ($devices as $device) {
            $deviceId = (int) $device['id'];
            if ($this->fcm === null) {
                $this->sent[] = ['token' => (string) $device['token'], 'data' => $data];
                $this->logger?->info('push (journal) user=' . $userId . ' ' . $category . ' : ' . $title);
                $delivered++;
                continue;
            }
            try {
                $result = $this->fcm->send((string) $device['token'], $data);
            } catch (PushException $exception) {
                $this->logger?->error('push : ' . $exception->getMessage());
                $this->pdo->prepare('UPDATE push_devices SET last_error = ? WHERE id = ?')->execute([mb_substr($exception->getMessage(), 0, 250), $deviceId]);
                continue;
            }
            if ($result['ok']) {
                $this->pdo->prepare('UPDATE push_devices SET last_sent_at = UTC_TIMESTAMP(), last_error = NULL WHERE id = ?')->execute([$deviceId]);
                $delivered++;
            } elseif ($result['unregistered']) {
                // Appareil désinscrit (navigateur réinitialisé, permission retirée) : on l'oublie.
                $this->pdo->prepare('DELETE FROM push_devices WHERE id = ?')->execute([$deviceId]);
            } else {
                $this->logger?->error('push user=' . $userId . ' : ' . $result['error']);
                $this->pdo->prepare('UPDATE push_devices SET last_error = ? WHERE id = ?')->execute([$result['error'], $deviceId]);
            }
        }

        return $delivered > 0 ? 'sent' : 'failed';
    }

    /**
     * Réserve une clé d'envoi unique ; faux si elle a déjà servi.
     */
    private function claim(string $key): bool
    {
        $statement = $this->pdo->prepare('INSERT IGNORE INTO notification_keys (key_hash, created_at) VALUES (?, UTC_TIMESTAMP())');
        $statement->execute([hash('sha256', $key)]);

        return $statement->rowCount() === 1;
    }
}
