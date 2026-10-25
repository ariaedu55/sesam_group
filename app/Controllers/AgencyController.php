<?php

declare(strict_types=1);

namespace Sesam\Controllers;

use Sesam\Controllers\Concerns\RendersAppArea;
use Sesam\Core\Auth\Permission;
use Sesam\Core\HttpException;
use Sesam\Core\Request\Request;
use Sesam\Core\Response\Response;
use Sesam\Core\Response\ViewRenderer;
use Sesam\Core\Security\Csrf;
use Sesam\Core\Session\Session;
use Sesam\Core\Tenant\TenantContext;
use Sesam\Core\Validation\ValidationException;
use Sesam\Models\User;
use Sesam\Services\Agency\AgencyCatalog;
use Sesam\Services\Agency\AgencyService;
use Sesam\Services\AuthService;
use Sesam\Services\NotificationService;

final class AgencyController extends Controller
{
    use RendersAppArea;

    private const PROSPECT_FIELDS = ['full_name', 'business_name', 'email', 'phone', 'activity', 'next_action_on'];

    public function __construct(
        ViewRenderer $views,
        private readonly Csrf $csrf,
        private readonly Session $session,
        private readonly AuthService $auth,
        private readonly TenantContext $context,
        private readonly AgencyService $agency,
        private readonly NotificationService $notifications,
    ) {
        parent::__construct($views);
    }

    public function dashboard(Request $request): Response
    {
        $actor = $this->auth->requirePermission(Permission::AGENCY_VIEW);

        return $this->page('staff/agence/dashboard', $this->agency->dashboard($actor) + [
            'title' => 'Agence — SESAM',
            'navTitle' => 'Agence SESAM Digital',
        ], $actor);
    }

    public function prospects(Request $request): Response
    {
        $actor = $this->auth->requirePermission(Permission::AGENCY_VIEW);
        $query = [];
        foreach (['q', 'etape', 'vue'] as $key) {
            $query[$key] = $request->input($key, '');
        }

        return $this->page('staff/agence/prospects', $this->agency->prospects($actor, $query) + [
            'title' => 'Prospects — Agence SESAM',
            'navTitle' => 'Prospects et clients',
        ], $actor);
    }

    public function prospectForm(Request $request): Response
    {
        $actor = $this->auth->requirePermission(Permission::AGENCY_PROSPECTS);

        return $this->prospectFormView($actor, null, array_fill_keys(self::PROSPECT_FIELDS, ''));
    }

    public function prospectStore(Request $request): Response
    {
        $actor = $this->auth->requirePermission(Permission::AGENCY_PROSPECTS);
        $input = $this->inputs($request, self::PROSPECT_FIELDS);
        try {
            $id = $this->agency->createProspect($actor, $input);
        } catch (ValidationException $exception) {
            return $this->prospectFormView($actor, null, $input, $exception->errors(), 422);
        }
        $this->session->put('status', 'Prospect ajouté au pipeline.');

        return Response::redirect('/agence/prospects/' . $id);
    }

    public function prospectShow(Request $request): Response
    {
        $actor = $this->auth->requirePermission(Permission::AGENCY_VIEW);

        return $this->prospectView($actor, $this->id($request));
    }

    public function prospectEditForm(Request $request): Response
    {
        $actor = $this->auth->requirePermission(Permission::AGENCY_PROSPECTS);
        $id = $this->id($request);
        $prospect = $this->agency->findProspect($actor, $id);

        return $this->prospectFormView($actor, $id, [
            'full_name' => $prospect->fullName(),
            'business_name' => $prospect->businessName(),
            'email' => $prospect->email(),
            'phone' => $prospect->phone(),
            'activity' => $prospect->activity(),
            'next_action_on' => $prospect->nextActionOn() ?? '',
        ]);
    }

    public function prospectUpdate(Request $request): Response
    {
        $actor = $this->auth->requirePermission(Permission::AGENCY_PROSPECTS);
        $id = $this->id($request);
        $input = $this->inputs($request, self::PROSPECT_FIELDS);
        try {
            $this->agency->updateProspect($actor, $id, $input);
        } catch (ValidationException $exception) {
            return $this->prospectFormView($actor, $id, $input, $exception->errors(), 422);
        }
        $this->session->put('status', 'Fiche mise à jour.');

        return Response::redirect('/agence/prospects/' . $id);
    }

    public function prospectStage(Request $request): Response
    {
        $actor = $this->auth->requirePermission(Permission::AGENCY_PROSPECTS);
        $id = $this->id($request);
        $input = $this->inputs($request, ['stage', 'note']);
        try {
            $message = $this->agency->changeStage($actor, $id, $input);
        } catch (ValidationException $exception) {
            return $this->prospectView($actor, $id, 'stage', $input, $exception->errors());
        }
        $this->session->put('status', $message);

        return Response::redirect('/agence/prospects/' . $id);
    }

    public function prospectNote(Request $request): Response
    {
        $actor = $this->auth->requirePermission(Permission::AGENCY_PROSPECTS);
        $id = $this->id($request);
        $input = $this->inputs($request, ['note', 'next_action_on']);
        try {
            $this->agency->addNote($actor, $id, $input);
        } catch (ValidationException $exception) {
            return $this->prospectView($actor, $id, 'note', $input, $exception->errors());
        }
        $this->session->put('status', 'Suivi enregistré.');

        return Response::redirect('/agence/prospects/' . $id);
    }

    public function quoteStore(Request $request): Response
    {
        $actor = $this->auth->requirePermission(Permission::AGENCY_QUOTES);
        $id = $this->id($request);
        $input = $this->inputs($request, ['offer', 'site_path', 'amount', 'description']);
        try {
            $message = $this->agency->createQuote($actor, $id, $input);
        } catch (ValidationException $exception) {
            return $this->prospectView($actor, $id, 'quote', $input, $exception->errors());
        }
        $this->session->put('status', $message);

        return Response::redirect('/agence/prospects/' . $id . '#devis');
    }

    public function quoteStatus(Request $request): Response
    {
        $actor = $this->auth->requirePermission(Permission::AGENCY_QUOTES);
        $prospectId = $this->positiveInt($request->input('prospect_id'));
        try {
            $result = $this->agency->quoteStatus($actor, $this->id($request), $this->inputs($request, ['status']));
        } catch (ValidationException $exception) {
            if ($prospectId === null) {
                throw $exception;
            }
            $this->session->put('status', $this->firstError($exception));

            return Response::redirect('/agence/prospects/' . $prospectId . '#devis');
        }
        $this->session->put('status', $result['message']);

        return Response::redirect('/agence/prospects/' . $result['prospectId'] . '#devis');
    }

    public function requests(Request $request): Response
    {
        $actor = $this->auth->requirePermission(Permission::AGENCY_VIEW);

        return $this->page('staff/agence/demandes', $this->agency->requests($actor, (string) $request->input('vue', '')) + [
            'title' => 'Demandes de devis — Agence SESAM',
            'navTitle' => 'Demandes de devis',
            'current' => 'demandes',
            'activities' => AgencyCatalog::activities(),
        ], $actor);
    }

    public function convertRequest(Request $request): Response
    {
        $actor = $this->auth->requirePermission(Permission::AGENCY_PROSPECTS);
        try {
            $id = $this->agency->convertRequest($actor, $this->id($request));
        } catch (ValidationException $exception) {
            $this->session->put('status', $this->firstError($exception));

            return Response::redirect('/agence/prospects');
        }
        $this->session->put('status', 'Demande du site transformée en prospect.');

        return Response::redirect('/agence/prospects/' . $id);
    }

    public function projects(Request $request): Response
    {
        $actor = $this->auth->requirePermission(Permission::AGENCY_VIEW);

        return $this->page('staff/agence/projects', $this->agency->projects($actor, ['statut' => $request->input('statut', '')]) + [
            'title' => 'Projets — Agence SESAM',
            'navTitle' => 'Projets web',
        ], $actor);
    }

    public function projectShow(Request $request): Response
    {
        $actor = $this->auth->requirePermission(Permission::AGENCY_VIEW);

        return $this->projectView($actor, $this->id($request));
    }

    public function projectStatus(Request $request): Response
    {
        $actor = $this->auth->requirePermission(Permission::AGENCY_PROJECTS);
        $id = $this->id($request);
        $input = $this->inputs($request, ['action', 'site_url']);
        try {
            $message = $this->agency->projectStatus($actor, $id, $input);
        } catch (ValidationException $exception) {
            return $this->projectView($actor, $id, 'status', $input, $exception->errors());
        }
        $this->session->put('status', $message);

        return Response::redirect('/agence/projets/' . $id);
    }

    public function invoiceStore(Request $request): Response
    {
        $actor = $this->auth->requirePermission(Permission::AGENCY_INVOICES);
        $id = $this->id($request);
        $input = $this->inputs($request, ['label', 'amount', 'due_on']);
        try {
            $message = $this->agency->createInvoice($actor, $id, $input);
        } catch (ValidationException $exception) {
            return $this->projectView($actor, $id, 'invoice', $input, $exception->errors());
        }
        $this->session->put('status', $message);

        return Response::redirect('/agence/projets/' . $id . '#factures');
    }

    public function invoicePay(Request $request): Response
    {
        $actor = $this->auth->requirePermission(Permission::AGENCY_INVOICES);
        $back = $request->input('retour') === 'factures' ? '/agence/factures' : null;
        try {
            $result = $this->agency->payInvoice($actor, $this->id($request), $this->inputs($request, ['payment_method', 'paid_on']));
        } catch (ValidationException $exception) {
            $this->session->put('status', $this->firstError($exception));

            return Response::redirect($back ?? '/agence/factures');
        }
        $this->session->put('status', $result['message']);

        return Response::redirect($back ?? '/agence/projets/' . $result['projectId'] . '#factures');
    }

    public function invoices(Request $request): Response
    {
        $actor = $this->auth->requirePermission(Permission::AGENCY_VIEW);

        return $this->page('staff/agence/invoices', $this->agency->invoices($actor, ['statut' => $request->input('statut', '')]) + [
            'title' => 'Factures — Agence SESAM',
            'navTitle' => 'Factures agence',
        ], $actor);
    }

    /**
     * @param array<string, mixed> $old
     * @param array<string, string> $errors
     */
    private function prospectView(User $actor, int $id, string $form = '', array $old = [], array $errors = []): Response
    {
        return $this->page('staff/agence/prospect', $this->agency->prospect($actor, $id) + [
            'title' => 'Prospect — Agence SESAM',
            'navTitle' => 'Fiche prospect',
            'form' => $form,
            'old' => $old,
            'errors' => $errors,
        ], $actor, $errors === [] ? 200 : 422);
    }

    /**
     * @param array<string, mixed> $old
     * @param array<string, string> $errors
     */
    private function projectView(User $actor, int $id, string $form = '', array $old = [], array $errors = []): Response
    {
        return $this->page('staff/agence/project', $this->agency->project($actor, $id) + [
            'title' => 'Projet — Agence SESAM',
            'navTitle' => 'Fiche projet',
            'form' => $form,
            'old' => $old,
            'errors' => $errors,
        ], $actor, $errors === [] ? 200 : 422);
    }

    /**
     * @param array<string, mixed> $old
     * @param array<string, string> $errors
     */
    private function prospectFormView(User $actor, ?int $id, array $old, array $errors = [], int $status = 200): Response
    {
        if ($actor->tenantId() !== null) {
            throw new HttpException(403, 'Vous n\'avez pas l\'autorisation d\'accéder à cette page.');
        }

        return $this->page('staff/agence/prospect-form', [
            'title' => ($id === null ? 'Nouveau prospect' : 'Modifier le prospect') . ' — Agence SESAM',
            'navTitle' => $id === null ? 'Nouveau prospect' : 'Modifier le prospect',
            'prospectId' => $id,
            'old' => $old,
            'errors' => $errors,
            'activities' => AgencyCatalog::activities(),
        ], $actor, $status);
    }

    /**
     * @param array<string, mixed> $data
     */
    private function page(string $template, array $data, User $actor, int $status = 200): Response
    {
        return $this->renderAppArea($template, $data + [
            'current' => 'agence',
            'canProspects' => $actor->can(Permission::AGENCY_PROSPECTS),
            'canQuotes' => $actor->can(Permission::AGENCY_QUOTES),
            'canProjects' => $actor->can(Permission::AGENCY_PROJECTS),
            'canInvoices' => $actor->can(Permission::AGENCY_INVOICES),
        ], $actor, $status);
    }

    /**
     * @param list<string> $keys
     * @return array<string, mixed>
     */
    private function inputs(Request $request, array $keys): array
    {
        $input = [];
        foreach ($keys as $key) {
            $input[$key] = $request->input($key);
        }

        return $input;
    }

    private function firstError(ValidationException $exception): string
    {
        $errors = $exception->errors();

        return (string) (reset($errors) ?: 'Action impossible.');
    }

    private function positiveInt(mixed $value): ?int
    {
        return is_string($value) && ctype_digit($value) && (int) $value > 0 ? (int) $value : null;
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
