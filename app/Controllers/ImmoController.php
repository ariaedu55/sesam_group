<?php

declare(strict_types=1);

namespace Sesam\Controllers;

use Sesam\Controllers\Concerns\RendersAppArea;
use Sesam\Controllers\Concerns\RequiresLoyerSector;
use Sesam\Core\Auth\Permission;
use Sesam\Core\HttpException;
use Sesam\Core\Immo\ImmoCatalog;
use Sesam\Core\Request\Request;
use Sesam\Core\Response\Response;
use Sesam\Core\Response\ViewRenderer;
use Sesam\Core\Security\Csrf;
use Sesam\Core\Session\Session;
use Sesam\Core\Tenant\TenantContext;
use Sesam\Core\Validation\ValidationException;
use Sesam\Models\User;
use Sesam\Services\AuthService;
use Sesam\Services\Immo\ImmoService;
use Sesam\Services\NotificationService;

/**
 * Espace Sesam Immo : loyers, locataires, biens, baux, interventions, relances, réglages et quittances.
 */
final class ImmoController extends Controller
{
    use RendersAppArea;
    use RequiresLoyerSector;

    private const TENANT_FIELDS = ['full_name', 'phone', 'email', 'id_type', 'id_number', 'profession', 'employer', 'emergency_name', 'emergency_phone', 'guarantor_name', 'guarantor_phone', 'notes'];
    private const PROPERTY_FIELDS = ['name', 'kind', 'address', 'city', 'owner_name', 'owner_phone', 'notes'];
    private const UNIT_FIELDS = ['label', 'kind', 'rooms', 'surface_m2', 'floor_label', 'default_rent_minor', 'default_charges_minor', 'default_deposit_minor', 'description', 'is_active'];
    private const LEASE_FIELDS = ['unit_id', 'client_id', 'full_name', 'phone', 'email', 'rent_minor', 'charges_minor', 'deposit_minor', 'deposit_paid', 'due_day', 'notice_months', 'starts_on', 'duration_months', 'notes'];
    private const SETTING_FIELDS = ['display_name', 'whatsapp_phone', 'email', 'address', 'receipt_footer', 'signature_name', 'default_due_day', 'grace_days', 'remind_before_days', 'remind_on_due', 'remind_after_days', 'auto_reminders', 'reminder_message', 'allow_partial', 'min_partial_minor', 'lease_end_alert_days'];

    public function __construct(
        ViewRenderer $views,
        private readonly Csrf $csrf,
        private readonly Session $session,
        private readonly AuthService $auth,
        private readonly TenantContext $context,
        private readonly ImmoService $immo,
        private readonly NotificationService $notifications,
    ) {
        parent::__construct($views);
    }

    // ---------------------------------------------------------------- Loyers

    public function dues(Request $request): Response
    {
        $actor = $this->actor(Permission::LOYER_VIEW);

        return $this->page('tenant/immo/loyers/index', 'Loyers', 'immo-loyers', $actor, [
            'data' => $this->immo->dues($actor, [
                'mois' => (string) $request->input('mois', ''),
                'vue' => (string) $request->input('vue', ''),
                'q' => (string) $request->input('q', ''),
            ]),
        ]);
    }

    public function due(Request $request): Response
    {
        $actor = $this->actor(Permission::LOYER_VIEW);

        return $this->dueView($actor, $this->id($request));
    }

    public function pay(Request $request): Response
    {
        $actor = $this->actor(Permission::LOYER_DUES);
        $id = $this->id($request);
        $input = $this->only($request, ['amount', 'method', 'paid_on', 'reference', 'note']);
        try {
            $this->flash($this->immo->recordPayment($actor, $id, $input));
        } catch (ValidationException $exception) {
            return $this->dueView($actor, $id, $input, $exception->errors(), 422);
        }

        return Response::redirect('/immo/loyers/' . $id);
    }

    public function link(Request $request): Response
    {
        $actor = $this->actor(Permission::LOYER_DUES);
        $id = $this->id($request);
        try {
            $paymentId = $this->immo->paymentLink($actor, $id, (string) $request->input('link_amount', ''));
        } catch (ValidationException $exception) {
            return $this->dueView($actor, $id, [], $exception->errors(), 422);
        }
        $this->flash('Lien de paiement créé. Envoyez-le au locataire ou simulez son paiement.');

        return Response::redirect('/paiements/' . $paymentId);
    }

    public function remind(Request $request): Response
    {
        $actor = $this->actor(Permission::LOYER_DUES);
        $id = $this->id($request);
        try {
            $this->flash($this->immo->remind($actor, $id, (string) $request->input('message', '')));
        } catch (ValidationException $exception) {
            return $this->dueView($actor, $id, [], $exception->errors(), 422);
        }

        return Response::redirect('/immo/loyers/' . $id . '#relances');
    }

    public function receipt(Request $request): Response
    {
        $actor = $this->actor(Permission::LOYER_VIEW);
        $data = $this->immo->receipt($actor, $this->id($request));

        return $this->render('tenant/immo/quittance', $data + ['title' => 'Quittance ' . ($data['payment']['receipt_number'] ?? '')], 200, 'layouts/print');
    }

    // ---------------------------------------------------------------- Locataires

    public function tenants(Request $request): Response
    {
        $actor = $this->actor(Permission::LOYER_VIEW);

        return $this->page('tenant/immo/locataires/index', 'Locataires', 'immo-locataires', $actor, [
            'data' => $this->immo->tenants($actor, trim((string) $request->input('q', '')), (string) $request->input('vue', '')),
            'canManage' => $actor->can(Permission::LOYER_LEASES),
        ]);
    }

    public function tenant(Request $request): Response
    {
        $actor = $this->actor(Permission::LOYER_VIEW);

        return $this->page('tenant/immo/locataires/show', 'Locataire', 'immo-locataires', $actor, [
            'file' => $this->immo->tenantFile($actor, $this->id($request)),
            'canManage' => $actor->can(Permission::LOYER_LEASES),
        ]);
    }

    public function tenantForm(Request $request): Response
    {
        $actor = $this->actor(Permission::LOYER_LEASES);
        $id = $request->attribute('id');
        $old = [];
        if ($id !== null) {
            $file = $this->immo->tenantFile($actor, $this->id($request));
            $old = $file['client'] + $file['profile'];
        }

        return $this->tenantFormView($actor, $id !== null ? (int) $id : null, $old, []);
    }

    public function tenantSave(Request $request): Response
    {
        $actor = $this->actor(Permission::LOYER_LEASES);
        $id = $request->attribute('id') !== null ? $this->id($request) : null;
        $input = $this->only($request, self::TENANT_FIELDS);
        try {
            $clientId = $this->immo->saveTenant($actor, $id, $input);
        } catch (ValidationException $exception) {
            return $this->tenantFormView($actor, $id, $input, $exception->errors(), 422);
        }
        $this->flash($id === null ? 'Locataire ajouté. Vous pouvez maintenant lui créer un contrat de bail.' : 'Fiche du locataire enregistrée.');

        return Response::redirect('/immo/locataires/' . $clientId);
    }

    // ---------------------------------------------------------------- Biens et logements

    public function properties(Request $request): Response
    {
        $actor = $this->actor(Permission::LOYER_VIEW);

        return $this->page('tenant/immo/biens/index', 'Biens', 'immo-biens', $actor, [
            'items' => $this->immo->properties($actor),
            'canManage' => $actor->can(Permission::LOYER_PROPERTIES),
        ]);
    }

    public function property(Request $request): Response
    {
        $actor = $this->actor(Permission::LOYER_VIEW);

        return $this->propertyView($actor, $this->id($request), [], []);
    }

    public function propertyForm(Request $request): Response
    {
        $actor = $this->actor(Permission::LOYER_PROPERTIES);
        $id = $request->attribute('id');

        return $this->propertyFormView($actor, $id !== null ? (int) $id : null, $id !== null ? $this->immo->property($actor, $this->id($request))['property'] : ['kind' => 'immeuble'], []);
    }

    public function propertySave(Request $request): Response
    {
        $actor = $this->actor(Permission::LOYER_PROPERTIES);
        $id = $request->attribute('id') !== null ? $this->id($request) : null;
        $input = $this->only($request, self::PROPERTY_FIELDS);
        try {
            $propertyId = $this->immo->saveProperty(
                $actor,
                $id,
                $input,
                $request->file('image'),
                $request->input('remove_image') === '1',
            );
        } catch (ValidationException $exception) {
            if ($id !== null) {
                $existing = $this->immo->property($actor, $id)['property'] ?? [];
                $input['image_path'] = $existing['image_path'] ?? null;
                $input['updated_at'] = $existing['updated_at'] ?? '';
            }

            return $this->propertyFormView($actor, $id, $input, $exception->errors(), 422);
        }
        $this->flash($id === null ? 'Bien ajouté. Ajoutez maintenant ses logements.' : 'Bien enregistré.');

        return Response::redirect('/immo/biens/' . $propertyId);
    }

    public function unitStore(Request $request): Response
    {
        $actor = $this->actor(Permission::LOYER_PROPERTIES);
        $propertyId = $this->id($request);
        $input = $this->only($request, self::UNIT_FIELDS);
        try {
            $this->immo->saveUnit($actor, $propertyId, null, $input);
        } catch (ValidationException $exception) {
            return $this->propertyView($actor, $propertyId, $input, $exception->errors(), 422);
        }
        $this->flash('Logement ajouté.');

        return Response::redirect('/immo/biens/' . $propertyId . '#logements');
    }

    public function unitForm(Request $request): Response
    {
        $actor = $this->actor(Permission::LOYER_PROPERTIES);
        $unit = $this->immo->unit($actor, $this->id($request));

        return $this->page('tenant/immo/biens/unit', 'Modifier le logement', 'immo-biens', $actor, ['unit' => $unit, 'old' => $unit, 'errors' => []]);
    }

    public function unitSave(Request $request): Response
    {
        $actor = $this->actor(Permission::LOYER_PROPERTIES);
        $unit = $this->immo->unit($actor, $this->id($request));
        $input = $this->only($request, self::UNIT_FIELDS);
        try {
            $this->immo->saveUnit($actor, (int) $unit['property_id'], (int) $unit['id'], $input);
        } catch (ValidationException $exception) {
            return $this->page('tenant/immo/biens/unit', 'Modifier le logement', 'immo-biens', $actor, ['unit' => $unit, 'old' => $input, 'errors' => $exception->errors()], 422);
        }
        $this->flash('Logement enregistré.');

        return Response::redirect('/immo/biens/' . $unit['property_id'] . '#logements');
    }

    // ---------------------------------------------------------------- Baux

    public function leases(Request $request): Response
    {
        $actor = $this->actor(Permission::LOYER_VIEW);

        return $this->page('tenant/immo/baux/index', 'Contrats de bail', 'immo-baux', $actor, [
            'data' => $this->immo->leases($actor, (string) $request->input('vue', '')),
            'canManage' => $actor->can(Permission::LOYER_LEASES),
        ]);
    }

    public function lease(Request $request): Response
    {
        $actor = $this->actor(Permission::LOYER_VIEW);

        return $this->leaseView($actor, $this->id($request), []);
    }

    public function leaseForm(Request $request): Response
    {
        $actor = $this->actor(Permission::LOYER_LEASES);
        $catalog = $this->immo->leaseFormCatalog($actor);

        return $this->leaseFormView($actor, $catalog, [
            'unit_id' => (string) $request->input('logement', ''),
            'client_id' => (string) $request->input('locataire', ''),
            'starts_on' => gmdate('Y-m-01', (int) strtotime('first day of next month')),
            'due_day' => (string) $catalog['settings']['default_due_day'],
            'notice_months' => '1',
            'duration_months' => '12',
        ], []);
    }

    public function leaseStore(Request $request): Response
    {
        $actor = $this->actor(Permission::LOYER_LEASES);
        $input = $this->only($request, self::LEASE_FIELDS);
        try {
            $id = $this->immo->createLease($actor, $input);
        } catch (ValidationException $exception) {
            return $this->leaseFormView($actor, $this->immo->leaseFormCatalog($actor), $input, $exception->errors(), 422);
        }
        $this->flash('Contrat de bail créé. Les échéances de loyer sont prêtes.');

        return Response::redirect('/immo/baux/' . $id);
    }

    public function deposit(Request $request): Response
    {
        $actor = $this->actor(Permission::LOYER_LEASES);
        $id = $this->id($request);
        try {
            $this->immo->markDeposit($actor, $id, (string) $request->input('deposit_paid_on', ''));
        } catch (ValidationException $exception) {
            return $this->leaseView($actor, $id, $exception->errors(), 422);
        }
        $this->flash('Caution notée comme versée.');

        return Response::redirect('/immo/baux/' . $id);
    }

    public function endLease(Request $request): Response
    {
        $actor = $this->actor(Permission::LOYER_LEASES);
        $id = $this->id($request);
        try {
            $this->immo->endLease($actor, $id, (string) $request->input('ended_on', ''), (string) $request->input('end_reason', ''));
        } catch (ValidationException $exception) {
            return $this->leaseView($actor, $id, $exception->errors(), 422);
        }
        $this->flash('Contrat de bail terminé. Le logement est de nouveau libre.');

        return Response::redirect('/immo/baux/' . $id);
    }

    // ---------------------------------------------------------------- Interventions

    public function interventions(Request $request): Response
    {
        $actor = $this->actor(Permission::LOYER_VIEW);

        return $this->interventionsView($actor, (string) $request->input('vue', ''), [], []);
    }

    public function interventionStore(Request $request): Response
    {
        $actor = $this->actor(Permission::LOYER_DUES);
        $input = $this->only($request, ['property_id', 'unit_id', 'title', 'description', 'priority', 'provider', 'cost_minor']);
        try {
            $this->immo->saveIntervention($actor, $input);
        } catch (ValidationException $exception) {
            return $this->interventionsView($actor, '', $input, $exception->errors(), 422);
        }
        $this->flash('Intervention ajoutée.');

        return Response::redirect('/immo/interventions');
    }

    public function interventionUpdate(Request $request): Response
    {
        $actor = $this->actor(Permission::LOYER_DUES);
        try {
            $this->immo->updateIntervention($actor, $this->id($request), $this->only($request, ['status', 'cost_minor', 'provider']));
        } catch (ValidationException $exception) {
            return $this->interventionsView($actor, '', [], $exception->errors(), 422);
        }
        $this->flash('Intervention mise à jour.');

        return Response::redirect('/immo/interventions');
    }

    // ---------------------------------------------------------------- Relances et réglages

    public function reminders(Request $request): Response
    {
        $actor = $this->actor(Permission::LOYER_VIEW);

        return $this->page('tenant/immo/relances', 'Relances', 'immo-relances', $actor, [
            'data' => $this->immo->reminders($actor),
            'canSend' => $actor->can(Permission::LOYER_DUES),
            'canSettings' => $actor->can(Permission::LOYER_SETTINGS),
        ]);
    }

    public function runReminders(Request $request): Response
    {
        $actor = $this->actor(Permission::LOYER_DUES);
        $count = $this->immo->runRemindersNow($actor);
        $this->flash($count > 0 ? $count . ' relance(s) envoyée(s) selon vos réglages.' : 'Aucune relance à envoyer aujourd\'hui : tout est à jour selon vos réglages.');

        return Response::redirect('/immo/relances');
    }

    public function settings(Request $request): Response
    {
        $actor = $this->actor(Permission::LOYER_SETTINGS);

        return $this->settingsView($actor, $this->immo->settings($actor), []);
    }

    public function saveSettings(Request $request): Response
    {
        $actor = $this->actor(Permission::LOYER_SETTINGS);
        $input = $this->only($request, self::SETTING_FIELDS);
        try {
            $this->immo->saveSettings($actor, $input);
        } catch (ValidationException $exception) {
            return $this->settingsView($actor, $input, $exception->errors(), 422);
        }
        $this->flash('Réglages enregistrés.');

        return Response::redirect('/immo/parametres');
    }

    /** Anciennes adresses du module Loyer. */
    public function legacy(Request $request): Response
    {
        $path = $request->path();
        $map = ['/loyer/echeances' => '/immo/loyers', '/loyer/contrats' => '/immo/baux', '/loyer/biens' => '/immo/biens'];
        foreach ($map as $old => $new) {
            if (str_starts_with($path, $old)) {
                return Response::redirect($new . substr($path, strlen($old)), 301);
            }
        }

        return Response::redirect('/espace', 301);
    }

    // ---------------------------------------------------------------- Vues

    /**
     * @param array<string, mixed> $old
     * @param array<string, string> $errors
     */
    private function dueView(User $actor, int $id, array $old = [], array $errors = [], int $status = 200): Response
    {
        return $this->page('tenant/immo/loyers/show', 'Loyer', 'immo-loyers', $actor, [
            'detail' => $this->immo->due($actor, $id),
            'old' => $old,
            'errors' => $errors,
            'canCollect' => $actor->can(Permission::LOYER_DUES),
            'canOnline' => $actor->can(Permission::PAYMENTS_CREATE),
        ], $status);
    }

    /**
     * @param array<string, mixed> $old
     * @param array<string, string> $errors
     */
    private function tenantFormView(User $actor, ?int $id, array $old, array $errors, int $status = 200): Response
    {
        return $this->page('tenant/immo/locataires/form', $id === null ? 'Nouveau locataire' : 'Modifier le locataire', 'immo-locataires', $actor, [
            'id' => $id, 'old' => $old, 'errors' => $errors, 'idTypes' => ImmoCatalog::ID_TYPES,
        ], $status);
    }

    /**
     * @param array<string, mixed> $old
     * @param array<string, string> $errors
     */
    private function propertyView(User $actor, int $id, array $old, array $errors, int $status = 200): Response
    {
        return $this->page('tenant/immo/biens/show', 'Bien', 'immo-biens', $actor, [
            'data' => $this->immo->property($actor, $id),
            'old' => $old,
            'errors' => $errors,
            'canManage' => $actor->can(Permission::LOYER_PROPERTIES),
            'canLease' => $actor->can(Permission::LOYER_LEASES),
        ], $status);
    }

    /**
     * @param array<string, mixed> $old
     * @param array<string, string> $errors
     */
    private function propertyFormView(User $actor, ?int $id, array $old, array $errors, int $status = 200): Response
    {
        return $this->page('tenant/immo/biens/form', $id === null ? 'Nouveau bien' : 'Modifier le bien', 'immo-biens', $actor, ['id' => $id, 'old' => $old, 'errors' => $errors], $status);
    }

    /**
     * @param array<string, string> $errors
     */
    private function leaseView(User $actor, int $id, array $errors, int $status = 200): Response
    {
        return $this->page('tenant/immo/baux/show', 'Contrat de bail', 'immo-baux', $actor, [
            'data' => $this->immo->lease($actor, $id),
            'errors' => $errors,
            'canManage' => $actor->can(Permission::LOYER_LEASES),
        ], $status);
    }

    /**
     * @param array<string, mixed> $catalog
     * @param array<string, mixed> $old
     * @param array<string, string> $errors
     */
    private function leaseFormView(User $actor, array $catalog, array $old, array $errors, int $status = 200): Response
    {
        return $this->page('tenant/immo/baux/form', 'Nouveau contrat de bail', 'immo-baux', $actor, ['catalog' => $catalog, 'old' => $old, 'errors' => $errors, 'idTypes' => ImmoCatalog::ID_TYPES], $status);
    }

    /**
     * @param array<string, mixed> $old
     * @param array<string, string> $errors
     */
    private function interventionsView(User $actor, string $view, array $old, array $errors, int $status = 200): Response
    {
        return $this->page('tenant/immo/interventions', 'Interventions', 'immo-interventions', $actor, [
            'data' => $this->immo->interventions($actor, $view),
            'old' => $old,
            'errors' => $errors,
            'canManage' => $actor->can(Permission::LOYER_DUES),
        ], $status);
    }

    /**
     * @param array<string, mixed> $values
     * @param array<string, string> $errors
     */
    private function settingsView(User $actor, array $values, array $errors, int $status = 200): Response
    {
        return $this->page('tenant/immo/parametres', 'Réglages Immo', 'immo-parametres', $actor, ['values' => $values, 'errors' => $errors], $status);
    }

    /**
     * @param array<string, mixed> $data
     */
    private function page(string $template, string $title, string $current, User $actor, array $data, int $status = 200): Response
    {
        return $this->renderAppArea($template, $data + ['title' => $title, 'navTitle' => $title, 'current' => $current], $actor, $status);
    }

    private function actor(string $permission): User
    {
        $actor = $this->auth->requirePermission($permission);
        $this->assertLoyerSector($this->context);

        return $actor;
    }

    private function flash(string $message): void
    {
        $this->session->put('status', $message);
    }

    /**
     * @param list<string> $keys
     * @return array<string, mixed>
     */
    private function only(Request $request, array $keys): array
    {
        $input = [];
        foreach ($keys as $key) {
            $value = $request->input($key);
            $input[$key] = is_scalar($value) ? (string) $value : '';
        }

        return $input;
    }

    private function id(Request $request): int
    {
        $raw = (string) $request->attribute('id', '');
        if (!ctype_digit($raw) || (int) $raw < 1) {
            throw new HttpException(404, 'Page introuvable.');
        }

        return (int) $raw;
    }
}
