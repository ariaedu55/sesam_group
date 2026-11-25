<?php

declare(strict_types=1);

namespace Sesam\Controllers;

use Sesam\Core\HttpException;
use Sesam\Core\Request\Request;
use Sesam\Core\Response\Response;
use Sesam\Core\Response\ViewRenderer;
use Sesam\Core\Security\Csrf;
use Sesam\Core\Session\Session;
use Sesam\Core\Tenant\Sector;
use Sesam\Services\AuthService;
use Sesam\Services\SaasService;

/**
 * « Mes activités » : un même compte ouvre plusieurs activités SESAM (Immo, Réservation, Boutique).
 * Après la connexion, le compte choisit l'activité à ouvrir ; il revient ici depuis le menu pour changer.
 */
final class ActivityController extends Controller
{
    /** @var array<string, array{product: string, pitch: string, image: string, icon: string, tone: string}> */
    public const PRODUCTS = [
        Sector::LOYER => [
            'product' => 'Sesam Immo',
            'pitch' => 'Loyers, locataires, biens et quittances.',
            'image' => 'assets/images/sesam-hero-immo.jpg',
            'icon' => 'building',
            'tone' => 'emerald',
        ],
        Sector::RESERVATION => [
            'product' => 'Sesam Réservation',
            'pitch' => 'Agenda, rendez-vous, prestations et équipe.',
            'image' => 'assets/images/sesam-hero-reservation.jpg',
            'icon' => 'calendar',
            'tone' => 'electric',
        ],
        Sector::BOUTIQUE => [
            'product' => 'Sesam Boutique',
            'pitch' => 'Commandes WhatsApp, produits et stock.',
            'image' => 'assets/images/sesam-hero-boutique.jpg',
            'icon' => 'bag',
            'tone' => 'amber',
        ],
    ];

    public function __construct(
        ViewRenderer $views,
        private readonly Csrf $csrf,
        private readonly Session $session,
        private readonly AuthService $auth,
        private readonly SaasService $saas,
    ) {
        parent::__construct($views);
    }

    public function index(Request $request): Response
    {
        $user = $this->auth->requireUser();
        $activities = $this->auth->activities($user);
        if ($activities === []) {
            return Response::redirect('/espace');
        }
        if (count($activities) === 1) {
            $this->auth->switchActivity($user, $activities[0]->id());

            return Response::redirect('/espace');
        }

        $cards = [];
        $locked = array_map(static fn ($t): array => ['tenant' => $t, 'locked' => true], $this->auth->lockedActivities($user));
        $entries = [...array_map(static fn ($t): array => ['tenant' => $t, 'locked' => false], $activities), ...$locked];
        foreach ($entries as ['tenant' => $tenant, 'locked' => $isLocked]) {
            $product = self::PRODUCTS[$tenant->sector()] ?? [
                'product' => 'Sesam ' . $tenant->sectorLabel(),
                'pitch' => '',
                'image' => 'assets/images/sesam-hero-web.jpg',
                'icon' => 'store',
                'tone' => 'blue',
            ];
            $cards[] = $product + [
                'id' => $tenant->id(),
                'name' => $tenant->displayName(),
                'slug' => $tenant->slug(),
                'open' => $tenant->isActive(),
                // Business du groupe auquel ce compte n'a pas accès : visible, grisé, non cliquable.
                'locked' => $isLocked,
                'current' => !$isLocked && $this->auth->activityChosen() && $tenant->id() === $user->tenantId(),
                'access' => $isLocked ? [] : $this->saas->access($tenant->id()),
            ];
        }
        $message = $this->session->get('status', '');
        $this->session->forget('status');

        return $this->render('app/activities/index', [
            'title' => 'Mes activités — SESAM',
            'actor' => $user,
            'cards' => $cards,
            'csrf' => $this->csrf->token(),
            'status' => is_string($message) ? $message : '',
        ], 200, 'layouts/hub');
    }

    public function open(Request $request): Response
    {
        $user = $this->auth->requireUser();
        $raw = (string) $request->attribute('id', '');
        if (!ctype_digit($raw)) {
            throw new HttpException(404, 'Activité introuvable.');
        }
        try {
            $this->auth->switchActivity($user, (int) $raw);
        } catch (HttpException $exception) {
            $this->session->put('status', $exception->getMessage());

            return Response::redirect('/mes-activites');
        }

        return Response::redirect('/espace');
    }
}
