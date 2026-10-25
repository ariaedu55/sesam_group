<?php

declare(strict_types=1);

namespace Sesam\Controllers;

use Sesam\Core\HttpException;
use Sesam\Core\Request\Request;
use Sesam\Core\Response\Response;
use Sesam\Core\Response\ViewRenderer;
use Sesam\Core\Security\Csrf;
use Sesam\Core\Validation\ValidationException;
use Sesam\Services\QuoteService;

final class SiteController extends Controller
{
    public function __construct(
        ViewRenderer $views,
        private readonly Csrf $csrf,
        private readonly QuoteService $quotes,
    ) {
        parent::__construct($views);
    }

    public function about(Request $request): Response
    {
        return $this->page('public/about', 'À propos — SESAM', 'a-propos');
    }

    public function services(Request $request): Response
    {
        return $this->page('public/services', 'Services — SESAM', 'services');
    }

    public function service(Request $request): Response
    {
        $key = (string) $request->attribute('key');
        if ($key === 'commande') {
            // Ancien service Commande, devenu Sesam Boutique.
            return Response::redirect('/services/boutique', 301);
        }
        if ($key === 'loyer') {
            // Sesam Loyer est devenu Sesam Immo.
            return Response::redirect('/services/immo', 301);
        }
        $catalog = require dirname(__DIR__) . '/Views/public/home-catalog.php';
        $service = $catalog['services'][$key] ?? null;
        if (!is_array($service)) {
            throw new HttpException(404, 'Page introuvable.');
        }

        return $this->render($key === 'boutique' ? 'public/service-boutique' : 'public/service', [
            'title' => $service['label'] . ' — SESAM',
            'current' => 'service-' . $key,
            'serviceKey' => $key,
        ], 200, 'layouts/public');
    }

    public function offers(Request $request): Response
    {
        return $this->page('public/offers', 'Offres — SESAM', 'offres');
    }

    public function portfolio(Request $request): Response
    {
        return $this->page('public/portfolio', 'Réalisations — SESAM', 'portfolio');
    }

    public function contact(Request $request): Response
    {
        return $this->page('public/contact', 'Contact — SESAM', 'contact');
    }

    public function quoteForm(Request $request): Response
    {
        $old = [];
        $activity = (string) $request->input('activite', '');
        if ($activity !== '') {
            $old['activity'] = $activity;
        }
        // Lien « Demander un devis pour ce site » d'une offre : budget pré-rempli.
        $offer = (string) $request->input('offre', '');
        $budgets = ['0' => '200-450k', '1' => '450k-1m', '2' => 'a-definir'];
        if (isset($budgets[$offer])) {
            $old['budget'] = $budgets[$offer];
        }

        return $this->quoteView($old);
    }

    public function quoteSubmit(Request $request): Response
    {
        $input = [];
        foreach (['full_name', 'business_name', 'email', 'phone', 'activity', 'message', 'site_state', 'site_type', 'current_site', 'has_domain', 'budget', 'deadline', 'inspiration'] as $key) {
            $value = $request->input($key);
            $input[$key] = is_scalar($value) ? (string) $value : '';
        }
        foreach (['pages', 'features'] as $key) {
            $value = $request->input($key);
            $input[$key] = is_array($value) ? array_values(array_filter($value, 'is_string')) : [];
        }

        try {
            $this->quotes->submit($input, $request->ip());
        } catch (ValidationException $exception) {
            return $this->quoteView($input, $exception->errors(), 422);
        }

        return $this->page('public/quote-sent', 'Demande envoyée — SESAM', 'devis');
    }

    /**
     * @param array<string, mixed> $old
     * @param array<string, string> $errors
     */
    private function quoteView(array $old, array $errors = [], int $status = 200): Response
    {
        return $this->render('public/quote', [
            'title' => 'Devis site web — SESAM',
            'current' => 'devis',
            'csrf' => $this->csrf->token(),
            'old' => $old,
            'errors' => $errors,
        ], $status, 'layouts/public');
    }

    private function page(string $template, string $title, string $current): Response
    {
        return $this->render($template, [
            'title' => $title,
            'current' => $current,
        ], 200, 'layouts/public');
    }
}
