<?php

declare(strict_types=1);

namespace Sesam\Controllers;

use Sesam\Core\Boutique\Money;
use Sesam\Core\HttpException;
use Sesam\Core\Request\Request;
use Sesam\Core\Response\Response;
use Sesam\Core\Response\ViewRenderer;
use Sesam\Core\Security\Csrf;
use Sesam\Core\Tenant\Sector;
use Sesam\Core\Validation\ValidationException;
use Sesam\Core\Boutique\WhatsApp;
use Sesam\Models\Tenant;
use Sesam\Services\Boutique\BoutiqueService;
use Sesam\Services\Immo\ImmoService;
use Sesam\Services\Reservation\BookingService;
use Sesam\Services\Reservation\SalonService;
use Sesam\Services\Portal\PublicPortalService;

final class PortalController extends Controller
{
    public function __construct(
        ViewRenderer $views,
        private readonly Csrf $csrf,
        private readonly PublicPortalService $portal,
        private readonly BoutiqueService $boutique,
        private readonly ?SalonService $salon = null,
        private readonly ?BookingService $booking = null,
        private readonly ?ImmoService $immo = null,
    ) {
        parent::__construct($views);
    }

    public function home(Request $request): Response
    {
        $tenant = $this->tenant($request);
        // Les visites des pages publiques font aussi partir les rappels clients dus (sans tâche planifiée).
        \Sesam\Services\ReminderPump::current()?->runIfDue();
        if ($tenant->sector() === Sector::BOUTIQUE) {
            return $this->shop($tenant, [], []);
        }
        if ($tenant->sector() === Sector::RESERVATION && $this->salon !== null) {
            return $this->salonPage($tenant, [], []);
        }
        if ($tenant->sector() === Sector::LOYER) {
            return $this->immoPage($tenant, [], []);
        }
        $payload = $this->portal->homePayload($tenant);

        return $this->screen('portal/home', $tenant, $payload + [
            'old' => [],
            'errors' => [],
        ]);
    }

    public function submit(Request $request): Response
    {
        $tenant = $this->tenant($request);
        $input = [
            'full_name' => $request->input('full_name'),
            'phone' => $request->input('phone'),
            'email' => $request->input('email'),
            'service_id' => $request->input('service_id'),
            'staff_id' => $request->input('staff_id'),
            'starts_at' => $request->input('starts_at'),
            'notes' => $request->input('notes'),
            'unit_label' => $request->input('unit_label'),
            'message' => $request->input('message'),
        ];
        try {
            $result = $this->portal->submit($tenant, $input, $request->ip());
        } catch (ValidationException $exception) {
            if ($tenant->sector() === Sector::RESERVATION && $this->salon !== null) {
                return $this->salonPage($tenant, $input, $exception->errors(), 422);
            }
            if ($tenant->sector() === Sector::LOYER) {
                return $this->immoPage($tenant, $input, $exception->errors(), 422);
            }
            $payload = $this->portal->homePayload($tenant);

            return $this->screen('portal/home', $tenant, $payload + [
                'old' => $input,
                'errors' => $exception->errors(),
            ], 422);
        }

        return Response::redirect($result['redirect']);
    }

    /**
     * Panier de la boutique : la commande est enregistrée, puis le visiteur part sur WhatsApp avec le message prêt.
     */
    public function order(Request $request): Response
    {
        $tenant = $this->tenant($request);
        if ($tenant->sector() !== Sector::BOUTIQUE) {
            throw new HttpException(404, 'Page introuvable.');
        }
        $items = $request->input('items');
        $input = [
            'items' => is_array($items) ? $items : [],
            'customer_name' => (string) $request->input('customer_name', ''),
            'customer_phone' => (string) $request->input('customer_phone', ''),
            'customer_address' => (string) $request->input('customer_address', ''),
            'note' => (string) $request->input('note', ''),
        ];
        try {
            $result = $this->boutique->publicOrder($tenant, $input, $request->ip());
        } catch (ValidationException $exception) {
            return $this->shop($tenant, $input, $exception->errors(), 422);
        }

        return Response::redirect($result['whatsapp'], 303);
    }

    public function productImage(Request $request): Response
    {
        $tenant = $this->tenant($request);
        $id = (int) $request->attribute('id', '0');
        if ($id < 1) {
            throw new HttpException(404, 'Image introuvable.');
        }

        return $this->image($this->boutique->productImage($tenant, $id));
    }

    /** Fiche publique d'un produit de boutique : photo, prix, description, et balises lues par WhatsApp. */
    public function product(Request $request): Response
    {
        $tenant = $this->tenant($request);
        $id = (int) $request->attribute('id', '0');
        if ($tenant->sector() !== Sector::BOUTIQUE || $id < 1) {
            throw new HttpException(404, 'Page introuvable.');
        }
        $data = $this->boutique->publicProduct($tenant, $id);

        $product = $data['product'];
        $summary = Money::format($product->priceMinor()) . ($product->isAvailable() ? '' : ' · épuisé')
            . ($product->description() !== '' ? ' — ' . mb_substr($product->description(), 0, 150) : '');

        return $this->render('portal/boutique/product', $data + [
            'title' => $product->name() . ' — ' . $tenant->displayName(),
            'tenant' => $tenant,
            'ogTitle' => $product->name() . ' — ' . Money::format($product->priceMinor()),
            'ogDescription' => $summary,
            'ogUrl' => $data['url'],
            'ogImage' => $data['imageUrl'] !== '' ? $data['imageUrl'] : null,
        ], 200, 'layouts/boutique');
    }

    public function prestationImage(Request $request): Response
    {
        $tenant = $this->tenant($request);
        $id = (int) $request->attribute('id', '0');
        if ($tenant->sector() !== Sector::RESERVATION || $this->booking === null || $id < 1) {
            throw new HttpException(404, 'Image introuvable.');
        }

        return $this->image($this->booking->prestationImage($tenant, $id));
    }

    public function propertyImage(Request $request): Response
    {
        $tenant = $this->tenant($request);
        $id = (int) $request->attribute('id', '0');
        if ($tenant->sector() !== Sector::LOYER || $this->immo === null || $id < 1) {
            throw new HttpException(404, 'Image introuvable.');
        }

        return $this->image($this->immo->propertyImage($tenant, $id));
    }

    public function logo(Request $request): Response
    {
        return $this->brand($this->tenant($request), 'logo');
    }

    public function cover(Request $request): Response
    {
        return $this->brand($this->tenant($request), 'cover');
    }

    /** Créneaux libres d'un salon pour une prestation et un jour (JSON). */
    public function slots(Request $request): Response
    {
        $tenant = $this->tenant($request);
        if ($tenant->sector() !== Sector::RESERVATION || $this->booking === null) {
            throw new HttpException(404, 'Page introuvable.');
        }
        $staff = (int) $request->input('personne', '0');
        $slots = $this->booking->publicSlots(
            (int) $request->input('prestation', '0'),
            (string) $request->input('date', ''),
            $staff > 0 ? $staff : null,
        );

        return Response::json((string) json_encode(['slots' => $slots], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE));
    }

    /**
     * @param 'logo'|'cover' $kind
     */
    private function brand(Tenant $tenant, string $kind): Response
    {
        if ($tenant->sector() === Sector::RESERVATION && $this->salon !== null) {
            return $this->image($this->salon->brandImage($tenant, $kind));
        }

        return $this->image($this->boutique->brandImage($tenant, $kind));
    }

    /**
     * @param array<string, mixed> $old
     * @param array<string, string> $errors
     */
    private function salonPage(Tenant $tenant, array $old, array $errors, int $status = 200): Response
    {
        $salon = $this->salon;
        if ($salon === null) {
            throw new HttpException(404, 'Page introuvable.');
        }

        return $this->render('portal/salon/home', $salon->publicSalon($tenant) + $this->portal->homePayload($tenant) + [
            'title' => $tenant->displayName() . ' — Réservation en ligne',
            'tenant' => $tenant,
            'csrf' => $this->csrf->token(),
            'old' => $old,
            'errors' => $errors,
        ], $status, 'layouts/salon');
    }

    /**
     * @param array<string, mixed> $old
     * @param array<string, string> $errors
     */
    private function immoPage(Tenant $tenant, array $old, array $errors, int $status = 200): Response
    {
        $payload = $this->portal->homePayload($tenant);
        $agency = $this->immo !== null ? $this->immo->settingsFor($tenant->id()) : [];
        $phone = is_string($agency['whatsapp_phone'] ?? null) ? (string) $agency['whatsapp_phone'] : '';
        $name = is_string($agency['display_name'] ?? null) && $agency['display_name'] !== ''
            ? (string) $agency['display_name']
            : $tenant->displayName();

        return $this->render('portal/immo/home', $payload + [
            'title' => $name . ' — Biens à louer',
            'tenant' => $tenant,
            'csrf' => $this->csrf->token(),
            'old' => $old,
            'errors' => $errors,
            'agency' => $agency,
            'whatsapp' => $phone !== '' && WhatsApp::isValid($phone)
                ? WhatsApp::link($phone, 'Bonjour, je vous contacte depuis la page de ' . $name . '.')
                : '',
        ], $status, 'layouts/immo');
    }

    public function confirmation(Request $request): Response
    {
        $tenant = $this->tenant($request);
        $token = trim((string) $request->input('k', ''));
        if ($token === '') {
            throw new HttpException(404, 'Page introuvable.');
        }
        $payload = $this->portal->confirmation($tenant, $token);

        return $this->screen('portal/confirmation', $tenant, $payload);
    }

    public function sandbox(Request $request): Response
    {
        $tenant = $this->tenant($request);
        $payment = $this->portal->publicPayment($tenant, $this->paymentId($request));

        return $this->screen('portal/sandbox', $tenant, ['payment' => $payment]);
    }

    public function sandboxConfirm(Request $request): Response
    {
        $tenant = $this->tenant($request);
        $result = $this->portal->sandboxConfirm($tenant, $this->paymentId($request));

        return Response::redirect($result['redirect']);
    }

    public function sandboxCancel(Request $request): Response
    {
        $tenant = $this->tenant($request);
        $result = $this->portal->sandboxCancel($tenant, $this->paymentId($request));

        return Response::redirect($result['redirect']);
    }

    public function returnFromProvider(Request $request): Response
    {
        $tenant = $this->tenant($request);
        $result = $this->portal->returnFromProvider($tenant, $this->paymentId($request));

        return Response::redirect($result['redirect']);
    }

    /**
     * @param array<string, mixed> $old
     * @param array<string, string> $errors
     */
    private function shop(Tenant $tenant, array $old, array $errors, int $status = 200): Response
    {
        return $this->render('portal/boutique/home', $this->boutique->publicShop($tenant) + [
            'title' => $tenant->displayName(),
            'tenant' => $tenant,
            'csrf' => $this->csrf->token(),
            'old' => $old,
            'errors' => $errors,
        ], $status, 'layouts/boutique');
    }

    /**
     * @param array{contents: string, mime: string} $file
     */
    private function image(array $file): Response
    {
        return new Response($file['contents'], 200, [
            'Content-Type' => $file['mime'],
            'Cache-Control' => 'public, max-age=86400',
        ]);
    }

    private function tenant(Request $request): Tenant
    {
        return $this->portal->requireTenant((string) $request->attribute('slug', ''));
    }

    private function paymentId(Request $request): int
    {
        $id = (int) $request->attribute('id', '0');
        if ($id < 1) {
            throw new HttpException(404, 'Page introuvable.');
        }

        return $id;
    }

    /**
     * @param array<string, mixed> $data
     */
    private function screen(string $template, Tenant $tenant, array $data, int $status = 200): Response
    {
        return $this->render($template, $data + [
            'title' => $tenant->displayName(),
            'tenant' => $tenant,
            'csrf' => $this->csrf->token(),
        ], $status, $tenant->sector() === Sector::LOYER ? 'layouts/immo' : 'layouts/portal');
    }
}
