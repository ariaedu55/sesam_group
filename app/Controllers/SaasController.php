<?php

declare(strict_types=1);

namespace Sesam\Controllers;

use Sesam\Controllers\Concerns\RendersAppArea;
use Sesam\Core\HttpException;
use Sesam\Core\Request\Request;
use Sesam\Core\Response\Response;
use Sesam\Core\Response\ViewRenderer;
use Sesam\Core\Security\Csrf;
use Sesam\Core\Session\Session;
use Sesam\Core\Tenant\Sector;
use Sesam\Core\Tenant\TenantContext;
use Sesam\Core\Validation\ValidationException;
use Sesam\Models\User;
use Sesam\Services\AuthService;
use Sesam\Services\NotificationService;
use Sesam\Services\SaasService;

/**
 * Inscription depuis le site (« Commencer ») et page « Abonnement » de l'espace commerce.
 */
final class SaasController extends Controller
{
    use RendersAppArea;

    private const SIGNUP_FIELDS = ['mode', 'full_name', 'business_name', 'phone', 'sector', 'email', 'password', 'password_confirm', 'terms'];

    public function __construct(
        ViewRenderer $views,
        private readonly Csrf $csrf,
        private readonly Session $session,
        private readonly AuthService $auth,
        private readonly TenantContext $context,
        private readonly SaasService $saas,
        private readonly NotificationService $notifications,
    ) {
        parent::__construct($views);
    }

    // ---------------------------------------------------------------- Inscription

    public function signupForm(Request $request): Response
    {
        if ($this->auth->user() !== null) {
            return Response::redirect('/espace');
        }
        $sector = (string) $request->input('service', '');
        $old = ['sector' => in_array($sector, Sector::ALL, true) ? $sector : ''];
        $pending = $this->auth->pendingGoogle();
        if ($pending !== null) {
            $old['full_name'] = $pending['name'];
            $old['email'] = $pending['email'];
        }

        return $this->signupView($old, []);
    }

    public function signup(Request $request): Response
    {
        if ($this->auth->user() !== null) {
            return Response::redirect('/espace');
        }
        $input = [];
        foreach (self::SIGNUP_FIELDS as $key) {
            $value = $request->input($key);
            $input[$key] = is_scalar($value) ? (string) $value : '';
        }
        $pending = $this->auth->pendingGoogle();
        if ($pending !== null) {
            $input['email'] = $pending['email'];
            $input['google_sub'] = $pending['sub'];
            if (trim($input['full_name']) === '') {
                $input['full_name'] = $pending['name'];
            }
        }
        try {
            $userId = $this->saas->signup($input, $request->ip());
        } catch (ValidationException $exception) {
            unset($input['password'], $input['password_confirm']);

            return $this->signupView($input, $exception->errors(), 422);
        }
        $this->auth->clearPendingGoogle();
        $this->auth->startSessionFor($userId);
        if (!$this->saas->trialEnabled()) {
            // Sans essai gratuit : le compte est créé, l'espace s'ouvre au paiement du premier mois.
            $this->session->put('status', 'Bienvenue sur SESAM ! Votre compte est créé : réglez votre premier mois pour ouvrir votre espace.');

            return Response::redirect('/abonnement');
        }
        $this->session->put('status', 'Bienvenue sur SESAM ! Votre espace est prêt et votre essai gratuit d\'un mois commence aujourd\'hui.');

        return Response::redirect('/espace');
    }

    /**
     * @param array<string, string> $old
     * @param array<string, string> $errors
     */
    private function signupView(array $old, array $errors, int $status = 200): Response
    {
        return $this->render('public/signup', [
            'title' => $this->saas->trialEnabled() ? 'Commencer gratuitement — 1 mois offert — SESAM' : 'Commencer — SESAM',
            'current' => 'commencer',
            'csrf' => $this->csrf->token(),
            'old' => $old,
            'errors' => $errors,
            'googleEnabled' => $this->auth->googleEnabled(),
            'googlePending' => $this->auth->pendingGoogle(),
            'googleConfig' => $this->auth->googleWebConfig(),
            'offer' => $this->saas->publicOffer(),
        ], $status, 'layouts/public');
    }

    // ---------------------------------------------------------------- Abonnement du commerce

    public function subscription(Request $request): Response
    {
        $actor = $this->tenantUser();

        return $this->renderAppArea('tenant/abonnement/index', [
            'title' => 'Abonnement',
            'navTitle' => 'Abonnement',
            'current' => 'abonnement',
            'page' => $this->saas->page($actor),
            'errors' => [],
        ], $actor);
    }

    public function checkout(Request $request): Response
    {
        $actor = $this->tenantUser();
        $months = (int) $request->input('months', '1');
        $method = (string) $request->input('method', '');
        try {
            if ($this->saas->isLivePayments()) {
                $result = $this->saas->startOnlineCheckout($actor, $months, $method);

                return Response::redirect($result['checkout_url']);
            }
            $checkout = $this->saas->checkout($actor, $months, $method);
        } catch (ValidationException $exception) {
            return $this->renderAppArea('tenant/abonnement/index', [
                'title' => 'Abonnement', 'navTitle' => 'Abonnement', 'current' => 'abonnement',
                'page' => $this->saas->page($actor), 'errors' => $exception->errors(),
            ], $actor, 422);
        } catch (HttpException $exception) {
            if ($exception->status() === 403) {
                throw $exception;
            }
            $this->session->put('status', $exception->getMessage());

            return Response::redirect('/abonnement');
        }

        return $this->renderAppArea('tenant/abonnement/checkout', [
            'title' => 'Paiement de l\'abonnement',
            'navTitle' => 'Abonnement',
            'current' => 'abonnement',
            'checkout' => $checkout,
        ], $actor);
    }

    public function confirm(Request $request): Response
    {
        $actor = $this->tenantUser();
        if ((string) $request->input('decision', 'pay') === 'cancel') {
            $this->session->put('status', 'Paiement abandonné : rien n\'a été débité.');

            return Response::redirect('/abonnement');
        }
        $message = $this->saas->confirmRenewal($actor, (int) $request->input('months', '1'), (string) $request->input('method', ''));
        $this->session->put('status', $message);

        return Response::redirect('/abonnement?paye=1');
    }

    public function returnFromProvider(Request $request): Response
    {
        $actor = $this->tenantUser();
        $raw = (string) $request->attribute('id', '');
        if (!ctype_digit($raw) || (int) $raw < 1) {
            throw new HttpException(404, 'Paiement introuvable.');
        }
        $message = $this->saas->finalizeOnlineCheckout($actor, (int) $raw);
        $this->session->put('status', $message);

        return Response::redirect('/abonnement?paye=1');
    }

    private function tenantUser(): User
    {
        $actor = $this->auth->requireUser();
        if ($actor->tenantId() === null) {
            throw new HttpException(403, 'Réservé aux espaces des commerces.');
        }

        return $actor;
    }
}
