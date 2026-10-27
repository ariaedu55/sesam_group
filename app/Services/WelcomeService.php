<?php

declare(strict_types=1);

namespace Sesam\Services;

use Sesam\Core\Database\Connection;
use Sesam\Core\Tenant\Sector;
use Sesam\Models\Tenant;
use Sesam\Models\User;

/**
 * Popup de bienvenue affiché juste après la connexion, en trois versions :
 *  - « first »  : première connexion d'un commerce → visite guidée en 3 écrans selon son métier ;
 *  - « back »   : retour d'un commerce → salutation du moment et chiffres du jour ;
 *  - « team »   : équipe SESAM → aperçu de la console (inscriptions, essais, échéances).
 * Le contenu est préparé ici ; partials/welcome.php l'affiche, welcome.js l'anime.
 */
final class WelcomeService
{
    private static ?self $current = null;
    private ?\PDO $pdo = null;

    /**
     * @param array{host: string, port: string, name: string, user: string, password: string} $database
     */
    public function __construct(private readonly array $database)
    {
    }

    public static function setCurrent(?self $service): void
    {
        self::$current = $service;
    }

    public static function current(): ?self
    {
        return self::$current;
    }

    /**
     * @param array{first?: bool} $flag
     * @return array<string, mixed>
     */
    public function payload(array $flag, User $user, ?Tenant $tenant, int $unread, ?string $now = null): array
    {
        $now ??= gmdate('Y-m-d H:i:s');
        $firstName = self::firstName($user->fullName());
        $hour = (int) substr($now, 11, 2);
        $base = [
            'firstName' => $firstName,
            'greeting' => $hour >= 5 && $hour < 12 ? 'Bonjour' : ($hour >= 12 && $hour < 18 ? 'Bon après-midi' : 'Bonsoir'),
            'moment' => $hour >= 5 && $hour < 12 ? 'morning' : ($hour >= 12 && $hour < 18 ? 'day' : 'night'),
            'date' => self::longDate($now),
            'unread' => $unread,
            'first' => !empty($flag['first']),
        ];
        try {
            if ($tenant === null || $user->tenantId() === null) {
                return $base + ['type' => 'team', 'tiles' => $this->teamTiles($now)];
            }
            if (!empty($flag['first'])) {
                return $base + ['type' => 'first', 'sector' => $tenant->sector(), 'business' => $tenant->displayName(), 'slides' => self::slides($tenant)];
            }

            return $base + ['type' => 'back', 'sector' => $tenant->sector(), 'business' => $tenant->displayName(), 'tiles' => $this->tenantTiles($tenant, $now)];
        } catch (\Throwable) {
            // Un chiffre indisponible ne doit jamais empêcher l'accueil : on affiche la version sans chiffres.
            return $base + ['type' => $tenant === null ? 'team' : 'back', 'sector' => $tenant?->sector() ?? '', 'business' => $tenant?->displayName() ?? 'SESAM', 'tiles' => []];
        }
    }

    public static function firstName(string $fullName): string
    {
        $parts = preg_split('/\s+/', trim($fullName)) ?: [];
        $first = (string) ($parts[0] ?? '');

        return $first !== '' ? mb_convert_case($first, MB_CASE_TITLE, 'UTF-8') : 'à vous';
    }

    private static function longDate(string $now): string
    {
        $days = ['', 'lundi', 'mardi', 'mercredi', 'jeudi', 'vendredi', 'samedi', 'dimanche'];
        $months = ['', 'janvier', 'février', 'mars', 'avril', 'mai', 'juin', 'juillet', 'août', 'septembre', 'octobre', 'novembre', 'décembre'];
        $ts = (int) strtotime($now . ' UTC');

        return ucfirst($days[(int) gmdate('N', $ts)]) . ' ' . gmdate('j', $ts) . ' ' . $months[(int) gmdate('n', $ts)];
    }

    /**
     * Visite guidée de la première connexion : trois écrans adaptés au métier.
     *
     * @return list<array{title: string, text: string, steps?: list<array{label: string, href: string}>}>
     */
    private static function slides(Tenant $tenant): array
    {
        $name = $tenant->displayName();

        return match ($tenant->sector()) {
            Sector::RESERVATION => [
                ['title' => 'Bienvenue, votre salon est en ligne', 'text' => $name . ' a maintenant son espace SESAM : agenda, réservations en ligne et rappels WhatsApp, réunis au même endroit.'],
                ['title' => '3 gestes pour bien démarrer', 'text' => 'Quelques minutes suffisent, et vos clientes peuvent réserver.', 'steps' => [
                    ['label' => 'Ajoutez vos prestations et leurs photos', 'href' => '/reservation/prestations'],
                    ['label' => 'Réglez vos horaires et votre logo', 'href' => '/reservation/salon'],
                    ['label' => 'Partagez votre lien de réservation', 'href' => '/espace'],
                ]],
                ['title' => 'Vos clientes réservent, même la nuit', 'text' => 'Chaque réservation arrive dans votre agenda, avec une alerte sur votre téléphone. Vous n\'avez plus qu\'à les accueillir.'],
            ],
            Sector::BOUTIQUE => [
                ['title' => 'Bienvenue, votre boutique est en ligne', 'text' => $name . ' a maintenant sa vitrine SESAM : catalogue, stock et commandes WhatsApp, réunis au même endroit.'],
                ['title' => '3 gestes pour bien démarrer', 'text' => 'Quelques minutes suffisent, et vos clients peuvent commander.', 'steps' => [
                    ['label' => 'Ajoutez vos produits et leurs photos', 'href' => '/boutique/produits'],
                    ['label' => 'Indiquez votre numéro WhatsApp et votre logo', 'href' => '/boutique/parametres'],
                    ['label' => 'Partagez le lien de votre boutique', 'href' => '/espace'],
                ]],
                ['title' => 'Vos clients commandent, même la nuit', 'text' => 'Chaque commande arrive dans votre espace et sur votre téléphone, avec le stock tenu à jour automatiquement.'],
            ],
            default => [
                ['title' => 'Bienvenue dans votre gestion locative', 'text' => $name . ' a maintenant son espace Sesam Immo : biens, locataires, loyers et quittances, réunis au même endroit.'],
                ['title' => '3 gestes pour bien démarrer', 'text' => 'Quelques minutes suffisent, et les relances se font toutes seules.', 'steps' => [
                    ['label' => 'Ajoutez vos biens et logements', 'href' => '/immo/biens'],
                    ['label' => 'Créez vos contrats de bail', 'href' => '/immo/baux'],
                    ['label' => 'Suivez et encaissez les loyers', 'href' => '/immo/loyers'],
                ]],
                ['title' => 'Les loyers se suivent tout seuls', 'text' => 'Rappels avant l\'échéance, relances en cas de retard, quittances en un clic : vous gardez l\'esprit tranquille.'],
            ],
        };
    }

    /**
     * @return list<array{value: int, label: string, href: string, icon: string}>
     */
    private function tenantTiles(Tenant $tenant, string $now): array
    {
        $id = $tenant->id();
        $today = substr($now, 0, 10);
        $tomorrow = gmdate('Y-m-d', (int) strtotime($today . ' +1 day'));
        $week = gmdate('Y-m-d H:i:s', (int) strtotime($now . ' -7 days'));

        return match ($tenant->sector()) {
            Sector::RESERVATION => [
                ['value' => $this->count("SELECT COUNT(*) FROM reservation_appointments WHERE tenant_id = ? AND starts_at >= ? AND starts_at < ? AND status IN ('pending', 'confirmed')", [$id, $today . ' 00:00:00', $tomorrow . ' 00:00:00']), 'label' => 'rendez-vous aujourd\'hui', 'href' => '/reservation/agenda', 'icon' => 'calendar'],
                ['value' => $this->count("SELECT COUNT(*) FROM reservation_appointments WHERE tenant_id = ? AND status = 'pending' AND starts_at >= ?", [$id, $now]), 'label' => 'à confirmer', 'href' => '/reservation/rendez-vous?statut=pending', 'icon' => 'clock'],
                ['value' => $this->count("SELECT COUNT(*) FROM reservation_appointments WHERE tenant_id = ? AND source = 'online' AND created_at >= ?", [$id, $week]), 'label' => 'réservations en ligne (7 j)', 'href' => '/reservation/rendez-vous', 'icon' => 'spark'],
            ],
            Sector::BOUTIQUE => [
                ['value' => $this->count("SELECT COUNT(*) FROM boutique_orders WHERE tenant_id = ? AND status = 'new'", [$id]), 'label' => 'commandes à traiter', 'href' => '/boutique/commandes?statut=new', 'icon' => 'bag'],
                ['value' => $this->count('SELECT COUNT(*) FROM boutique_products WHERE tenant_id = ? AND is_published = 1 AND track_stock = 1 AND stock_quantity <= low_stock_threshold', [$id]), 'label' => 'produits en stock bas', 'href' => '/boutique/stock', 'icon' => 'box'],
                ['value' => $this->count('SELECT COUNT(*) FROM boutique_orders WHERE tenant_id = ? AND created_at >= ?', [$id, $week]), 'label' => 'commandes (7 j)', 'href' => '/boutique/commandes', 'icon' => 'spark'],
            ],
            default => [
                ['value' => $this->count("SELECT COUNT(*) FROM loyer_rent_dues d JOIN loyer_leases l ON l.id = d.lease_id WHERE d.tenant_id = ? AND l.status = 'active' AND d.status IN ('pending', 'partial') AND d.due_date >= ? AND d.due_date < ?", [$id, substr($today, 0, 8) . '01', gmdate('Y-m-01', (int) strtotime(substr($today, 0, 8) . '01 +1 month'))]), 'label' => 'loyers à encaisser ce mois', 'href' => '/immo/loyers', 'icon' => 'calendar'],
                ['value' => $this->count("SELECT COUNT(*) FROM loyer_rent_dues d JOIN loyer_leases l ON l.id = d.lease_id WHERE d.tenant_id = ? AND l.status = 'active' AND d.status IN ('pending', 'partial') AND d.due_date < ?", [$id, $today]), 'label' => 'loyers en retard', 'href' => '/immo/loyers?vue=retards', 'icon' => 'clock'],
                ['value' => $this->count("SELECT COUNT(*) FROM loyer_leases WHERE tenant_id = ? AND status = 'active'", [$id]), 'label' => 'contrats de bail actifs', 'href' => '/immo/baux', 'icon' => 'home'],
            ],
        };
    }

    /**
     * @return list<array{value: int, label: string, href: string, icon: string}>
     */
    private function teamTiles(string $now): array
    {
        $today = substr($now, 0, 10);

        return [
            ['value' => $this->count('SELECT COUNT(*) FROM tenants WHERE created_at >= ?', [gmdate('Y-m-d H:i:s', (int) strtotime($now . ' -7 days'))]), 'label' => 'nouveaux commerces (7 j)', 'href' => '/commerces', 'icon' => 'spark'],
            ['value' => $this->count("SELECT COUNT(*) FROM tenant_subscriptions WHERE status = 'trial' AND period_end BETWEEN ? AND ?", [$today, gmdate('Y-m-d', (int) strtotime($today . ' +3 days'))]), 'label' => 'essais finissent sous 3 j', 'href' => '/commerces?abonnement=trial', 'icon' => 'clock'],
            ['value' => $this->count("SELECT COUNT(*) FROM tenant_subscriptions s JOIN tenants t ON t.id = s.tenant_id WHERE t.status = 'active' AND s.status = 'active' AND s.period_end < ?", [$today]), 'label' => 'abonnements échus', 'href' => '/espace#echeances', 'icon' => 'calendar'],
        ];
    }

    /**
     * @param list<int|string> $params
     */
    private function count(string $sql, array $params): int
    {
        $this->pdo ??= Connection::make($this->database);
        $statement = $this->pdo->prepare($sql);
        $statement->execute($params);

        return (int) $statement->fetchColumn();
    }
}
