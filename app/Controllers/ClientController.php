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
use Sesam\Models\Client;
use Sesam\Models\User;
use Sesam\Services\AuthService;
use Sesam\Services\Boutique\BoutiqueService;
use Sesam\Services\Reservation\BookingService;
use Sesam\Services\ClientService;
use Sesam\Services\NotificationService;

final class ClientController extends Controller
{
    use RendersAppArea;

    public function __construct(
        ViewRenderer $views,
        private readonly Csrf $csrf,
        private readonly Session $session,
        private readonly AuthService $auth,
        private readonly ClientService $clients,
        private readonly TenantContext $context,
        private readonly NotificationService $notifications,
        private readonly ?BoutiqueService $boutique = null,
        private readonly ?BookingService $booking = null,
    ) {
        parent::__construct($views);
    }

    public function index(Request $request): Response
    {
        $actor = $this->auth->requirePermission(Permission::CLIENTS_VIEW);
        $query = trim((string) $request->input('q', ''));
        $status = (string) $request->input('status', '');
        $tagRaw = $request->input('tag', '');
        $tagId = is_numeric($tagRaw) ? (int) $tagRaw : null;
        $page = (int) $request->input('page', 1);
        $list = $this->clients->paginate($actor, $query, $status, $tagId, $page);

        return $this->renderAppArea('tenant/clients/index', [
            'title' => 'Clients — SESAM',
            'navTitle' => 'Clients',
            'current' => 'clients',
            'list' => $list,
            'canCreate' => $actor->can(Permission::CLIENTS_CREATE),
            'canExport' => $actor->can(Permission::CLIENTS_EXPORT),
        ], $actor);
    }

    public function export(Request $request): Response
    {
        $actor = $this->auth->requirePermission(Permission::CLIENTS_EXPORT);
        $query = trim((string) $request->input('q', ''));
        $status = (string) $request->input('status', '');
        $tagRaw = $request->input('tag', '');
        $tagId = is_numeric($tagRaw) ? (int) $tagRaw : null;
        $rows = $this->clients->exportRows($actor, $query, $status, $tagId);
        $lines = ['Nom;E-mail;Téléphone;Statut;Créé le'];
        foreach ($rows as $client) {
            $lines[] = implode(';', [
                $this->csvField($client->fullName()),
                $this->csvField($client->email() ?? ''),
                $this->csvField($client->phone() ?? ''),
                $this->csvField($client->isArchived() ? 'archivé' : 'actif'),
                $this->csvField($client->createdAt()),
            ]);
        }
        $csv = "\xEF\xBB\xBF" . implode("\r\n", $lines);

        return new Response($csv, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="clients-' . gmdate('Y-m-d') . '.csv"',
        ]);
    }

    public function createForm(Request $request): Response
    {
        $actor = $this->auth->requirePermission(Permission::CLIENTS_CREATE);

        return $this->formView($actor, null, [
            'full_name' => '',
            'email' => '',
            'phone' => '',
            'tag_ids' => [],
            'new_tags' => '',
        ]);
    }

    public function store(Request $request): Response
    {
        $actor = $this->auth->requirePermission(Permission::CLIENTS_CREATE);
        $input = $this->formInput($request);
        try {
            $id = $this->clients->create($actor, $input);
        } catch (ValidationException $exception) {
            return $this->formView($actor, null, $input, $exception->errors(), 422);
        }

        $this->session->put('status', 'Le client a été enregistré.');

        return Response::redirect('/clients/' . $id);
    }

    public function show(Request $request): Response
    {
        $actor = $this->auth->requirePermission(Permission::CLIENTS_VIEW);
        $detail = $this->clients->detail($actor, $this->id($request));

        return $this->renderAppArea('tenant/clients/show', [
            'title' => $detail['client']->fullName() . ' — Clients',
            'navTitle' => 'Fiche client',
            'current' => 'clients',
            'client' => $detail['client'],
            'notes' => $detail['notes'],
            'activities' => $detail['activities'],
            'allTags' => $detail['allTags'],
            'canUpdate' => $actor->can(Permission::CLIENTS_UPDATE),
            'canNotes' => $actor->can(Permission::CLIENTS_NOTES),
            'canArchive' => $actor->can(Permission::CLIENTS_ARCHIVE),
            'boutiqueOrders' => $this->boutique?->ordersForClient($actor, $detail['client']->id()) ?? [],
            'canOrder' => $actor->can(Permission::BOUTIQUE_ORDERS),
            'appointments' => $this->booking?->appointmentsForClient($actor, $detail['client']->id()) ?? [],
            'canBook' => $actor->can(Permission::RESERVATION_APPOINTMENTS),
        ], $actor);
    }

    public function editForm(Request $request): Response
    {
        $actor = $this->auth->requirePermission(Permission::CLIENTS_UPDATE);
        $detail = $this->clients->detail($actor, $this->id($request));
        $client = $detail['client'];

        return $this->formView($actor, $client, [
            'full_name' => $client->fullName(),
            'email' => $client->email() ?? '',
            'phone' => $client->phone() ?? '',
            'tag_ids' => array_map(static fn ($tag) => $tag->id(), $client->tags()),
            'new_tags' => '',
        ]);
    }

    public function update(Request $request): Response
    {
        $actor = $this->auth->requirePermission(Permission::CLIENTS_UPDATE);
        $id = $this->id($request);
        $input = $this->formInput($request);
        try {
            $this->clients->update($actor, $id, $input);
        } catch (ValidationException $exception) {
            $client = $this->clients->find($actor, $id);

            return $this->formView($actor, $client, $input, $exception->errors(), 422);
        }

        $this->session->put('status', 'La fiche client a été mise à jour.');

        return Response::redirect('/clients/' . $id);
    }

    public function storeNote(Request $request): Response
    {
        $actor = $this->auth->requirePermission(Permission::CLIENTS_NOTES);
        $id = $this->id($request);
        try {
            $this->clients->addNote($actor, $id, (string) $request->input('body', ''));
        } catch (ValidationException $exception) {
            $this->session->put('status', implode(' ', $exception->errors()));

            return Response::redirect('/clients/' . $id);
        }

        $this->session->put('status', 'Note ajoutée.');

        return Response::redirect('/clients/' . $id);
    }

    public function archive(Request $request): Response
    {
        $actor = $this->auth->requirePermission(Permission::CLIENTS_ARCHIVE);
        $id = $this->id($request);
        $this->clients->archive($actor, $id);
        $this->session->put('status', 'Client archivé.');

        return Response::redirect('/clients/' . $id);
    }

    public function restore(Request $request): Response
    {
        $actor = $this->auth->requirePermission(Permission::CLIENTS_ARCHIVE);
        $id = $this->id($request);
        $this->clients->restore($actor, $id);
        $this->session->put('status', 'Client restauré.');

        return Response::redirect('/clients/' . $id);
    }

    /**
     * @return array<string, mixed>
     */
    private function formInput(Request $request): array
    {
        return [
            'full_name' => $request->input('full_name'),
            'email' => $request->input('email'),
            'phone' => $request->input('phone'),
            'tag_ids' => $request->input('tag_ids', []),
            'new_tags' => $request->input('new_tags'),
        ];
    }

    /**
     * @param array<string, mixed> $old
     * @param array<string, string> $errors
     */
    private function formView(User $actor, ?Client $client, array $old, array $errors = [], int $status = 200): Response
    {
        return $this->renderAppArea('tenant/clients/form', [
            'title' => ($client === null ? 'Nouveau client' : 'Modifier le client') . ' — SESAM',
            'navTitle' => $client === null ? 'Nouveau client' : 'Modifier',
            'current' => 'clients',
            'edited' => $client,
            'old' => $old,
            'errors' => $errors,
            'allTags' => $this->clients->allTags($actor),
            'canTags' => $actor->can(Permission::CLIENTS_TAGS),
        ], $actor, $status);
    }

    private function id(Request $request): int
    {
        $raw = (string) $request->attribute('id', '');
        if (!ctype_digit($raw) || (int) $raw < 1) {
            throw new HttpException(404, 'Client introuvable.');
        }

        return (int) $raw;
    }

    private function csvField(string $value): string
    {
        $value = str_replace('"', '""', $value);

        return '"' . $value . '"';
    }
}
