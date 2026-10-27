<?php

declare(strict_types=1);

namespace Sesam\Controllers;

use Sesam\Controllers\Concerns\RendersAppArea;
use Sesam\Controllers\Concerns\RendersListPartial;
use Sesam\Controllers\Concerns\RequiresReservationSector;
use Sesam\Core\Auth\Permission;
use Sesam\Core\HttpException;
use Sesam\Core\Request\Request;
use Sesam\Core\Reservation\AppointmentStatus;
use Sesam\Core\Response\Response;
use Sesam\Core\Response\ViewRenderer;
use Sesam\Core\Security\Csrf;
use Sesam\Core\Session\Session;
use Sesam\Core\Tenant\TenantContext;
use Sesam\Core\Validation\ValidationException;
use Sesam\Models\User;
use Sesam\Services\AuthService;
use Sesam\Services\NotificationService;
use Sesam\Services\Reservation\BookingService;

final class ReservationAppointmentController extends Controller
{
    use RendersAppArea;
    use RendersListPartial;
    use RequiresReservationSector;

    private const STATUS_MESSAGES = [
        AppointmentStatus::CONFIRMED => 'Rendez-vous confirmé. Le rappel WhatsApp est prévu la veille.',
        AppointmentStatus::COMPLETED => 'Rendez-vous marqué comme terminé.',
        AppointmentStatus::NO_SHOW => 'Client noté absent.',
        AppointmentStatus::CANCELLED => 'Rendez-vous annulé : le créneau est de nouveau libre.',
        AppointmentStatus::PENDING => 'Rendez-vous réactivé.',
    ];

    public function __construct(
        ViewRenderer $views,
        private readonly Csrf $csrf,
        private readonly Session $session,
        private readonly AuthService $auth,
        private readonly TenantContext $context,
        private readonly BookingService $booking,
        private readonly NotificationService $notifications,
    ) {
        parent::__construct($views);
    }

    public function index(Request $request): Response
    {
        $actor = $this->auth->requirePermission(Permission::RESERVATION_VIEW);
        $this->assertReservationSector($this->context);
        $filters = [
            'status' => trim((string) $request->input('statut', '')),
            'period' => trim((string) $request->input('periode', 'a-venir')),
            'q' => trim((string) $request->input('q', '')),
            'staff_id' => (int) $request->input('personne', '0'),
        ];

        $data = [
            'title' => 'Rendez-vous',
            'navTitle' => 'Rendez-vous',
            'current' => 'reservation-rdv',
            'list' => $this->booking->paginateAppointments($actor, (int) $request->input('page', 1), $filters),
            'filters' => $filters,
            'team' => $this->booking->listTeam($actor),
            'canManage' => $actor->can(Permission::RESERVATION_APPOINTMENTS),
        ];
        $partial = $this->renderListPartial($request, 'tenant/reservation/rendez-vous/_results', $data);
        if ($partial !== null) {
            return $partial;
        }

        return $this->renderAppArea('tenant/reservation/rendez-vous/index', $data, $actor);
    }

    public function createForm(Request $request): Response
    {
        $actor = $this->auth->requirePermission(Permission::RESERVATION_APPOINTMENTS);
        $this->assertReservationSector($this->context);

        return $this->form($actor, [
            'client_id' => (string) $request->input('client', ''),
            'service_id' => '',
            'staff_id' => (string) $request->input('personne', ''),
            'date' => (string) $request->input('date', gmdate('Y-m-d')),
            'starts_at' => '',
            'notes' => '',
        ], []);
    }

    public function store(Request $request): Response
    {
        $actor = $this->auth->requirePermission(Permission::RESERVATION_APPOINTMENTS);
        $this->assertReservationSector($this->context);
        $input = [
            'client_id' => $request->input('client_id'),
            'client_name' => $request->input('client_name'),
            'client_phone' => $request->input('client_phone'),
            'service_id' => $request->input('service_id'),
            'staff_id' => $request->input('staff_id'),
            'starts_at' => $request->input('starts_at'),
            'notes' => $request->input('notes'),
            'confirm_now' => $request->input('confirm_now'),
        ];
        try {
            $id = $this->booking->createAppointment($actor, $input);
        } catch (ValidationException $exception) {
            return $this->form($actor, $input + ['date' => substr((string) $input['starts_at'], 0, 10)], $exception->errors(), 422);
        }
        $this->session->put('status', 'Rendez-vous ajouté à l\'agenda.');

        return Response::redirect('/reservation/rendez-vous/' . $id);
    }

    public function show(Request $request): Response
    {
        $actor = $this->auth->requirePermission(Permission::RESERVATION_VIEW);
        $this->assertReservationSector($this->context);

        return $this->detail($actor, $this->id($request));
    }

    public function status(Request $request): Response
    {
        return $this->move($request, (string) $request->input('status', ''));
    }

    public function confirm(Request $request): Response
    {
        return $this->move($request, AppointmentStatus::CONFIRMED);
    }

    public function cancel(Request $request): Response
    {
        return $this->move($request, AppointmentStatus::CANCELLED);
    }

    public function paid(Request $request): Response
    {
        $actor = $this->auth->requirePermission(Permission::RESERVATION_APPOINTMENTS);
        $id = $this->id($request);
        $paid = $this->booking->togglePaid($actor, $id);
        $this->session->put('status', $paid ? 'Prestation marquée comme payée.' : 'Prestation marquée comme non payée.');

        return Response::redirect('/reservation/rendez-vous/' . $id);
    }

    public function rescheduleForm(Request $request): Response
    {
        $actor = $this->auth->requirePermission(Permission::RESERVATION_APPOINTMENTS);
        $this->assertReservationSector($this->context);

        return $this->rescheduleView($actor, $this->id($request), []);
    }

    public function reschedule(Request $request): Response
    {
        $actor = $this->auth->requirePermission(Permission::RESERVATION_APPOINTMENTS);
        $this->assertReservationSector($this->context);
        $id = $this->id($request);
        try {
            $this->booking->reschedule($actor, $id, [
                'starts_at' => $request->input('starts_at'),
                'staff_id' => $request->input('staff_id'),
            ]);
        } catch (ValidationException $exception) {
            return $this->rescheduleView($actor, $id, $exception->errors(), 422);
        }
        $this->session->put('status', 'Rendez-vous déplacé.');

        return Response::redirect('/reservation/rendez-vous/' . $id);
    }

    public function deposit(Request $request): Response
    {
        $actor = $this->auth->requirePermission(Permission::RESERVATION_APPOINTMENTS);
        $this->assertReservationSector($this->context);
        $id = $this->id($request);
        try {
            $url = $this->booking->initiateDeposit($actor, $id);
        } catch (ValidationException $exception) {
            $this->session->put('status', implode(' ', $exception->errors()));

            return Response::redirect('/reservation/rendez-vous/' . $id);
        }

        return Response::redirect($url);
    }

    /** Créneaux libres en JSON pour l'écran « Nouveau rendez-vous » et « Déplacer ». */
    public function slots(Request $request): Response
    {
        $actor = $this->auth->requirePermission(Permission::RESERVATION_VIEW);
        $staff = (int) $request->input('personne', '0');
        $slots = $this->booking->slotsFor(
            $actor,
            (int) $request->input('prestation', '0'),
            (string) $request->input('date', ''),
            $staff > 0 ? $staff : null,
        );

        return Response::json((string) json_encode(['slots' => $slots], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE));
    }

    /** Suggestions de clientes pendant la saisie du nom (JSON). */
    public function clientSearch(Request $request): Response
    {
        $actor = $this->auth->requirePermission(Permission::RESERVATION_APPOINTMENTS);
        $this->assertReservationSector($this->context);
        $clients = $this->booking->clientSuggestions($actor, (string) $request->input('q', ''));

        return Response::json((string) json_encode(['clients' => $clients], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE));
    }

    private function move(Request $request, string $status): Response
    {
        $actor = $this->auth->requirePermission(Permission::RESERVATION_APPOINTMENTS);
        $this->assertReservationSector($this->context);
        $id = $this->id($request);
        try {
            $this->booking->changeStatus($actor, $id, $status);
        } catch (ValidationException $exception) {
            return $this->detail($actor, $id, $exception->errors(), 422);
        }
        $this->session->put('status', self::STATUS_MESSAGES[$status] ?? 'Rendez-vous mis à jour.');
        $back = (string) $request->input('retour', '');

        return Response::redirect(str_starts_with($back, '/reservation/') ? $back : '/reservation/rendez-vous/' . $id);
    }

    /**
     * @param array<string, string> $errors
     */
    private function detail(User $actor, int $id, array $errors = [], int $status = 200): Response
    {
        $detail = $this->booking->appointmentDetail($actor, $id);

        return $this->renderAppArea('tenant/reservation/rendez-vous/show', [
            'title' => 'Rendez-vous — ' . $detail['appointment']->clientName(),
            'navTitle' => 'Rendez-vous',
            'current' => 'reservation-rdv',
            'detail' => $detail,
            'appointment' => $detail['appointment'],
            'when' => $this->booking->human($detail['appointment']->startsAt()),
            'errors' => $errors,
            'canManage' => $actor->can(Permission::RESERVATION_APPOINTMENTS),
            'canPay' => $actor->can(Permission::PAYMENTS_CREATE),
        ], $actor, $status);
    }

    /**
     * @param array<string, mixed> $old
     * @param array<string, string> $errors
     */
    private function form(User $actor, array $old, array $errors, int $status = 200): Response
    {
        return $this->renderAppArea('tenant/reservation/rendez-vous/form', [
            'title' => 'Nouveau rendez-vous',
            'navTitle' => 'Nouveau rendez-vous',
            'current' => 'reservation-rdv',
            'catalog' => $this->booking->formCatalog($actor, (int) ($old['client_id'] ?? 0)),
            'old' => $old,
            'errors' => $errors,
        ], $actor, $status);
    }

    /**
     * @param array<string, string> $errors
     */
    private function rescheduleView(User $actor, int $id, array $errors, int $status = 200): Response
    {
        return $this->renderAppArea('tenant/reservation/rendez-vous/reschedule', [
            'title' => 'Déplacer le rendez-vous',
            'navTitle' => 'Déplacer',
            'current' => 'reservation-rdv',
            'appointment' => $this->booking->findAppointment($actor, $id),
            'catalog' => $this->booking->formCatalog($actor),
            'errors' => $errors,
        ], $actor, $status);
    }

    private function id(Request $request): int
    {
        $raw = (string) $request->attribute('id', '');
        if (!ctype_digit($raw) || (int) $raw < 1) {
            throw new HttpException(404, 'Rendez-vous introuvable.');
        }

        return (int) $raw;
    }
}
