<?php

declare(strict_types=1);

namespace Sesam\Services;

use Sesam\Core\Database\Connection;
use Sesam\Core\HttpException;
use Sesam\Core\Validation\ValidationException;
use Sesam\Core\Validation\Validator;
use Sesam\Repositories\AuthAttemptRepository;
use Sesam\Repositories\QuoteRepository;

/**
 * Demande de devis pour un site web (Sesam Digital) : le projet est décrit point par point pour préparer le devis.
 * Les outils SaaS (Réservation, Boutique, Immo) ne passent plus par un devis : ils s'activent depuis « Commencer ».
 */
final class QuoteService extends Service
{
    public const RATE_MAX = 5;
    public const RATE_WINDOW = 60;

    /** @var array<string, string> */
    public const SITE_STATES = ['nouveau' => 'Je n\'ai pas encore de site', 'refonte' => 'J\'ai un site à refaire'];

    /** @var array<string, string> */
    public const SITE_TYPES = [
        'vitrine' => 'Site vitrine (présenter mon activité)',
        'e-commerce' => 'Boutique en ligne',
        'reservation' => 'Site avec réservation',
        'catalogue' => 'Catalogue / portfolio',
        'evenement' => 'Événement ou lancement',
        'autre' => 'Autre',
    ];

    /** @var list<string> */
    public const PAGES = ['Accueil', 'À propos', 'Services / prestations', 'Galerie photos', 'Tarifs', 'Blog / actualités', 'FAQ', 'Contact'];

    /** @var list<string> */
    public const FEATURES = ['Bouton WhatsApp', 'Formulaire de contact', 'Paiement Wave / Orange Money', 'Réservation en ligne', 'Vente en ligne', 'Plusieurs langues', 'Carte Google Maps', 'Référencement Google'];

    /** @var array<string, string> */
    public const DOMAINS = ['oui' => 'Oui, j\'ai déjà un nom de domaine', 'non' => 'Non, à réserver', 'inconnu' => 'Je ne sais pas'];

    /** @var array<string, string> */
    public const BUDGETS = [
        'moins-200k' => 'Moins de 200 000 FCFA',
        '200-450k' => '200 000 à 450 000 FCFA',
        '450k-1m' => '450 000 à 1 000 000 FCFA',
        'plus-1m' => 'Plus de 1 000 000 FCFA',
        'a-definir' => 'À définir ensemble',
    ];

    /** @var array<string, string> */
    public const DEADLINES = [
        'urgent' => 'Moins de 2 semaines',
        '1-mois' => 'Dans le mois',
        '2-3-mois' => 'Dans 2 à 3 mois',
        'pas-presse' => 'Pas pressé',
    ];

    /**
     * @param array{host: string, port: string, name: string, user: string, password: string} $database
     */
    public function __construct(
        private readonly array $database,
        private readonly Validator $validator = new Validator(),
    ) {
    }

    /**
     * @param array<string, mixed> $input
     */
    public function submit(array $input, string $ip): void
    {
        foreach ($input as $key => $value) {
            if (is_string($value)) {
                $input[$key] = trim($value);
            }
        }

        $data = $this->validator->validate($input, [
            'full_name' => 'required|string|max:120',
            'email' => 'required|email|max:190',
            'phone' => 'required|string|max:30',
            'activity' => 'required|string|max:40',
            'message' => 'required|string|max:2000',
        ]);

        $catalog = require dirname(__DIR__) . '/Views/public/home-catalog.php';
        $errors = [];
        if (!in_array($data['activity'], array_keys($catalog['activities']), true)) {
            $errors['activity'] = 'Choisissez une activité proposée.';
        }
        $state = (string) ($input['site_state'] ?? '');
        $type = (string) ($input['site_type'] ?? '');
        if (!isset(self::SITE_STATES[$state])) {
            $errors['site_state'] = 'Dites-nous si vous partez de zéro ou si vous avez déjà un site.';
        }
        if (!isset(self::SITE_TYPES[$type])) {
            $errors['site_type'] = 'Choisissez le type de site.';
        }
        if ($errors !== []) {
            throw new ValidationException($errors);
        }
        $pick = static fn (mixed $values, array $allowed): array => is_array($values)
            ? array_values(array_intersect($allowed, array_map('strval', $values)))
            : [];
        $pages = $pick($input['pages'] ?? [], self::PAGES);
        $features = $pick($input['features'] ?? [], self::FEATURES);
        $domain = isset(self::DOMAINS[(string) ($input['has_domain'] ?? '')]) ? (string) $input['has_domain'] : null;
        $budget = isset(self::BUDGETS[(string) ($input['budget'] ?? '')]) ? (string) $input['budget'] : null;
        $deadline = isset(self::DEADLINES[(string) ($input['deadline'] ?? '')]) ? (string) $input['deadline'] : null;
        $business = mb_substr((string) ($input['business_name'] ?? ''), 0, 120);
        $inspiration = mb_substr((string) ($input['inspiration'] ?? ''), 0, 500);
        $currentSite = mb_substr((string) ($input['current_site'] ?? ''), 0, 200);

        $pdo = Connection::make($this->database);
        $attempts = new AuthAttemptRepository($pdo);
        if ($attempts->countRecent('quote', $ip, self::RATE_WINDOW) >= self::RATE_MAX) {
            throw new HttpException(429, 'Trop de demandes envoyées. Réessayez dans une heure.');
        }

        // Le message garde tout le projet lisible d'un coup d'œil (repris dans la fiche prospect).
        $summary = array_filter([
            'Site : ' . self::SITE_TYPES[$type] . ' — ' . mb_strtolower(self::SITE_STATES[$state]) . ($currentSite !== '' ? ' (' . $currentSite . ')' : ''),
            $pages !== [] ? 'Pages : ' . implode(', ', $pages) : null,
            $features !== [] ? 'Fonctions : ' . implode(', ', $features) : null,
            $domain !== null ? 'Nom de domaine : ' . self::DOMAINS[$domain] : null,
            $budget !== null ? 'Budget : ' . self::BUDGETS[$budget] : null,
            $deadline !== null ? 'Délai : ' . self::DEADLINES[$deadline] : null,
            $inspiration !== '' ? 'Exemples aimés : ' . $inspiration : null,
        ]);

        (new QuoteRepository($pdo))->insert([
            'full_name' => (string) $data['full_name'],
            'business_name' => $business !== '' ? $business : null,
            'email' => (string) $data['email'],
            'phone' => (string) $data['phone'],
            'activity' => (string) $data['activity'],
            'site_type' => $type,
            'site_state' => $state,
            'pages' => $pages !== [] ? mb_substr(implode(', ', $pages), 0, 255) : null,
            'features' => $features !== [] ? mb_substr(implode(', ', $features), 0, 255) : null,
            'has_domain' => $domain,
            'budget' => $budget,
            'deadline' => $deadline,
            'inspiration' => $inspiration !== '' ? $inspiration : null,
            'message' => mb_substr((string) $data['message'] . "\n\n" . implode("\n", $summary), 0, 4000),
        ]);
        $attempts->record('quote', $ip);
        Notifier::staff(
            'sesam.quote',
            'Demande de devis : ' . (string) $data['full_name'] . ($business !== '' ? ' (' . $business . ')' : ''),
            self::SITE_TYPES[$type] . ($budget !== null ? ' · ' . self::BUDGETS[$budget] : '') . ($deadline !== null ? ' · ' . self::DEADLINES[$deadline] : ''),
            '/agence/demandes',
        );
    }
}
