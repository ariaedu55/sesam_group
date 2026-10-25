<?php

declare(strict_types=1);

namespace Sesam\Services\Reservation;

use Sesam\Core\Auth\Permission;
use Sesam\Core\Boutique\BoutiqueTheme;
use Sesam\Core\Boutique\WhatsApp;
use Sesam\Core\Database\Connection;
use Sesam\Core\HttpException;
use Sesam\Core\Reservation\AppointmentStatus;
use Sesam\Core\Tenant\Sector;
use Sesam\Core\Tenant\TenantContext;
use Sesam\Core\Validation\ValidationException;
use Sesam\Models\ReservationSettings;
use Sesam\Models\Tenant;
use Sesam\Models\User;
use Sesam\Repositories\ReservationAppointmentRepository;
use Sesam\Repositories\ReservationPrestationRepository;
use Sesam\Repositories\ReservationSettingsRepository;
use Sesam\Repositories\ReservationTeamRepository;
use Sesam\Repositories\TenantRepository;
use Sesam\Services\Service;
use Sesam\Services\Storage\ImageUploadService;

/**
 * Le salon lui-même : sa page publique, ses horaires, ses fermetures, ses règles de réservation et son tableau de bord.
 */
final class SalonService extends Service
{
    private readonly ReservationSettingsRepository $settings;
    private readonly ReservationAppointmentRepository $appointments;
    private readonly ReservationPrestationRepository $prestations;
    private readonly ReservationTeamRepository $team;
    private readonly TenantRepository $tenants;

    /**
     * @param array{host: string, port: string, name: string, user: string, password: string} $database
     */
    public function __construct(
        array $database,
        private readonly TenantContext $context,
        private readonly ImageUploadService $images,
        private readonly string $appUrl,
    ) {
        $pdo = Connection::make($database);
        $this->settings = new ReservationSettingsRepository($pdo);
        $this->appointments = new ReservationAppointmentRepository($pdo);
        $this->prestations = new ReservationPrestationRepository($pdo);
        $this->team = new ReservationTeamRepository($pdo);
        $this->tenants = new TenantRepository($pdo);
    }

    /**
     * @return array<string, mixed>
     */
    public function dashboard(User $actor): array
    {
        $tenant = $this->tenant($actor, Permission::RESERVATION_VIEW);
        $tenantId = $tenant->id();
        $settings = $this->settings->find($tenantId);
        $todayStart = gmdate('Y-m-d') . ' 00:00:00';
        $tomorrow = gmdate('Y-m-d', strtotime('+1 day')) . ' 00:00:00';
        $weekStart = gmdate('Y-m-d', strtotime('monday this week')) . ' 00:00:00';
        $weekEnd = gmdate('Y-m-d', strtotime('monday next week')) . ' 00:00:00';
        $monthStart = gmdate('Y-m-01') . ' 00:00:00';
        $monthEnd = gmdate('Y-m-01', strtotime('first day of next month')) . ' 00:00:00';
        $monthCounts = $this->appointments->countByStatus($tenantId, $monthStart, $monthEnd);
        $done = $monthCounts[AppointmentStatus::COMPLETED] + $monthCounts[AppointmentStatus::NO_SHOW];
        $shareText = $tenant->displayName() . ' — réservez votre rendez-vous en ligne : ' . $this->publicUrl($tenant);

        return [
            'tenant' => $tenant,
            'settings' => $settings,
            'publicUrl' => $this->publicUrl($tenant),
            'shareLink' => 'https://wa.me/?text=' . rawurlencode($shareText),
            'shareText' => $shareText,
            'today' => $this->appointments->listBetween($tenantId, $todayStart, $tomorrow),
            'pending' => $this->appointments->search($tenantId, ['status' => AppointmentStatus::PENDING, 'from' => gmdate('Y-m-d H:i:s')], 6, 0, true),
            'countToday' => $this->appointments->countToday($tenantId, $todayStart, $tomorrow),
            'countPending' => $this->appointments->countPending($tenantId, gmdate('Y-m-d H:i:s')),
            'revenueWeek' => $this->appointments->revenueBetween($tenantId, $weekStart, $weekEnd),
            'noShowRate' => $done > 0 ? (int) round($monthCounts[AppointmentStatus::NO_SHOW] * 100 / $done) : 0,
            'checklist' => [
                ['label' => 'Régler les horaires d\'ouverture', 'done' => $settings->hours() !== [], 'href' => '/reservation/salon#horaires'],
                ['label' => 'Ajouter le numéro WhatsApp du salon', 'done' => $settings->whatsappPhone() !== '', 'href' => '/reservation/salon'],
                ['label' => 'Ajouter vos prestations et leurs prix', 'done' => $this->prestations->listActive($tenantId) !== [], 'href' => '/reservation/prestations/nouveau'],
                ['label' => 'Ajouter votre équipe', 'done' => $this->team->listActive($tenantId) !== [], 'href' => '/reservation/equipe/nouveau'],
            ],
        ];
    }

    public function settingsFor(User $actor): ReservationSettings
    {
        return $this->settings->find($this->tenant($actor, Permission::RESERVATION_VIEW)->id());
    }

    /**
     * @return list<array{id: int, staff_id: ?int, staff_name: string, starts_on: string, ends_on: string, reason: string}>
     */
    public function closures(User $actor): array
    {
        return $this->settings->closures($this->tenant($actor, Permission::RESERVATION_VIEW)->id(), gmdate('Y-m-d'), null, true);
    }

    public function publicUrlFor(User $actor): string
    {
        return $this->publicUrl($this->tenant($actor, Permission::RESERVATION_VIEW));
    }

    /**
     * @param array<string, mixed> $input
     * @param array<string, mixed>|null $logo
     * @param array<string, mixed>|null $cover
     */
    public function save(User $actor, array $input, ?array $logo, ?array $cover): void
    {
        $tenantId = $this->tenant($actor, Permission::RESERVATION_SETTINGS)->id();
        $errors = [];
        $name = trim((string) ($input['display_name'] ?? ''));
        if ($name === '' || mb_strlen($name) > 120) {
            $errors['display_name'] = 'Indiquez le nom du salon (120 caractères max.).';
        }
        $rawPhone = trim((string) ($input['whatsapp_phone'] ?? ''));
        $phone = $rawPhone === '' ? '' : WhatsApp::normalize($rawPhone);
        if ($rawPhone !== '' && !WhatsApp::isValid($phone)) {
            $errors['whatsapp_phone'] = 'Numéro WhatsApp invalide. Exemple : 77 123 45 67.';
        }
        $texts = [];
        foreach (['tagline' => 160, 'description' => 1000, 'address' => 190, 'cancellation_policy' => 500] as $key => $max) {
            $value = trim((string) ($input[$key] ?? ''));
            if (mb_strlen($value) > $max) {
                $errors[$key] = 'Texte trop long (' . $max . ' caractères max.).';
            }
            $texts[$key] = $value !== '' ? $value : null;
        }
        $numbers = [];
        foreach (['slot_step_minutes' => [5, 120], 'min_notice_hours' => [0, 168], 'max_advance_days' => [1, 365], 'buffer_minutes' => [0, 120]] as $key => [$min, $max]) {
            $value = (int) ($input[$key] ?? $min);
            if ($value < $min || $value > $max) {
                $errors[$key] = 'Valeur entre ' . $min . ' et ' . $max . '.';
            }
            $numbers[$key] = $value;
        }
        $hours = $this->parseHours($input, $errors);
        if ($errors !== []) {
            throw new ValidationException($errors);
        }
        $theme = (string) ($input['theme'] ?? 'ocean');

        $current = $this->settings->find($tenantId);
        $logoPath = $this->hasUpload($logo) ? $this->images->store($logo ?? [], 'salon/' . $tenantId) : null;
        try {
            $coverPath = $this->hasUpload($cover) ? $this->images->store($cover ?? [], 'salon/' . $tenantId) : null;
        } catch (ValidationException $exception) {
            $this->images->delete($logoPath);
            throw new ValidationException(['cover' => $exception->errors()['image'] ?? 'Image invalide.']);
        }

        $this->tenants->updateDisplayName($tenantId, $name);
        $data = $texts + $numbers + [
            'whatsapp_phone' => $phone !== '' ? $phone : null,
            'theme' => BoutiqueTheme::isValid($theme) ? $theme : 'ocean',
            'is_open' => !empty($input['is_open']) ? 1 : 0,
            'auto_confirm' => !empty($input['auto_confirm']) ? 1 : 0,
        ];
        foreach (['logo_path' => [$logoPath, $current->logoPath(), 'remove_logo'], 'cover_path' => [$coverPath, $current->coverPath(), 'remove_cover']] as $column => [$new, $old, $flag]) {
            if ($new !== null) {
                $data[$column] = $new;
                $this->images->delete($old);
            } elseif (!empty($input[$flag]) && $old !== null) {
                $data[$column] = null;
                $this->images->delete($old);
            }
        }
        $this->settings->save($tenantId, $data);
        $this->settings->replaceHours($tenantId, $hours);
    }

    /**
     * @param array<string, mixed> $input
     */
    public function addClosure(User $actor, array $input): void
    {
        $tenantId = $this->tenant($actor, Permission::RESERVATION_SETTINGS)->id();
        $from = trim((string) ($input['starts_on'] ?? ''));
        $to = trim((string) ($input['ends_on'] ?? '')) ?: $from;
        $reason = trim((string) ($input['reason'] ?? ''));
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $from) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $to) || $to < $from) {
            throw new ValidationException(['starts_on' => 'Indiquez une date de début et une date de fin valides.']);
        }
        if (mb_strlen($reason) > 160) {
            throw new ValidationException(['reason' => 'Motif trop long.']);
        }
        $this->settings->insertClosure($tenantId, null, $from, $to, $reason !== '' ? $reason : null);
    }

    public function deleteClosure(User $actor, int $id): void
    {
        $tenantId = $this->tenant($actor, Permission::RESERVATION_SETTINGS)->id();
        if (!$this->settings->deleteClosure($tenantId, $id)) {
            throw new HttpException(404, 'Fermeture introuvable.');
        }
    }

    // ---------------------------------------------------------------- Page publique

    /**
     * @return array{settings: ReservationSettings, whatsapp: string, closures: list<array{id: int, staff_id: ?int, staff_name: string, starts_on: string, ends_on: string, reason: string}>}
     */
    public function publicSalon(Tenant $tenant): array
    {
        $this->assertSalon($tenant);
        $settings = $this->settings->find($tenant->id());

        return [
            'settings' => $settings,
            'whatsapp' => $settings->whatsappPhone() !== ''
                ? WhatsApp::link($settings->whatsappPhone(), 'Bonjour ' . $tenant->displayName() . ', j\'ai une question sur une réservation.')
                : '',
            'closures' => $this->settings->closures($tenant->id(), gmdate('Y-m-d'), null, true),
        ];
    }

    /**
     * @param 'logo'|'cover' $kind
     * @return array{contents: string, mime: string}
     */
    public function brandImage(Tenant $tenant, string $kind): array
    {
        $this->assertSalon($tenant);
        $settings = $this->settings->find($tenant->id());
        $path = $kind === 'logo' ? $settings->logoPath() : $settings->coverPath();
        if ($path === null) {
            throw new HttpException(404, 'Image introuvable.');
        }

        return $this->images->read($path);
    }

    public function publicUrl(Tenant $tenant): string
    {
        return rtrim($this->appUrl, '/') . '/t/' . rawurlencode($tenant->slug());
    }

    /**
     * Horaires saisis dans le formulaire : jusqu'à deux plages par jour (matin, après-midi), cases « fermé ».
     *
     * @param array<string, mixed> $input
     * @param array<string, string> $errors
     * @return list<array{weekday: int, opens_at: string, closes_at: string}>
     */
    private function parseHours(array $input, array &$errors): array
    {
        $rows = [];
        for ($day = 1; $day <= 7; ++$day) {
            if (empty($input['day_' . $day . '_open'])) {
                continue;
            }
            foreach (['a', 'b'] as $part) {
                $from = trim((string) ($input['day_' . $day . '_' . $part . '_from'] ?? ''));
                $to = trim((string) ($input['day_' . $day . '_' . $part . '_to'] ?? ''));
                if ($from === '' && $to === '') {
                    continue;
                }
                if (!preg_match('/^\d{2}:\d{2}$/', $from) || !preg_match('/^\d{2}:\d{2}$/', $to) || $from >= $to) {
                    $errors['day_' . $day] = 'Horaires invalides pour ' . strtolower(BookingService::weekdayLabel($day)) . '.';
                    continue;
                }
                $rows[] = ['weekday' => $day, 'opens_at' => $from . ':00', 'closes_at' => $to . ':00'];
            }
        }

        return $rows;
    }

    /**
     * @param array<string, mixed>|null $file
     */
    private function hasUpload(?array $file): bool
    {
        return is_array($file) && (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE;
    }

    private function tenant(User $actor, string $permission): Tenant
    {
        if (!$actor->can($permission)) {
            throw new HttpException(403, 'Vous n\'avez pas l\'autorisation d\'accéder à cette page.');
        }
        $tenant = $this->context->tenant();
        if ($actor->tenantId() === null || !$tenant instanceof Tenant || $tenant->id() !== $actor->tenantId()) {
            throw new HttpException(403, 'Vous n\'avez pas l\'autorisation d\'accéder à cette page.');
        }
        if ($tenant->sector() !== Sector::RESERVATION) {
            throw new HttpException(403, 'Ce module est réservé aux commerces secteur Réservation.');
        }

        return $tenant;
    }

    private function assertSalon(Tenant $tenant): void
    {
        $bound = $this->context->tenant();
        if (!$bound instanceof Tenant || $bound->id() !== $tenant->id() || $tenant->sector() !== Sector::RESERVATION) {
            throw new HttpException(404, 'Page introuvable.');
        }
    }
}
