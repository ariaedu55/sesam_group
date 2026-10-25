<?php

declare(strict_types=1);

namespace Sesam\Controllers;

use Sesam\Controllers\Concerns\RendersAppArea;
use Sesam\Controllers\Concerns\RequiresReservationSector;
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
use Sesam\Services\AuthService;
use Sesam\Services\NotificationService;
use Sesam\Services\Reservation\BookingService;

final class ReservationTeamController extends Controller
{
    use RendersAppArea;
    use RequiresReservationSector;

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
        $prestations = [];
        foreach ($this->booking->listPrestations($actor) as $prestation) {
            $prestations[$prestation->id()] = $prestation->name();
        }

        return $this->renderAppArea('tenant/reservation/equipe/index', [
            'title' => 'Équipe',
            'navTitle' => 'Équipe',
            'current' => 'reservation-equipe',
            'items' => $this->booking->listTeam($actor),
            'prestations' => $prestations,
            'canManage' => $actor->can(Permission::RESERVATION_STAFF),
        ], $actor);
    }

    public function createForm(Request $request): Response
    {
        $actor = $this->auth->requirePermission(Permission::RESERVATION_STAFF);
        $this->assertReservationSector($this->context);

        return $this->createView($actor, ['name' => '', 'role_label' => '', 'phone' => '', 'service_ids' => []], []);
    }

    public function store(Request $request): Response
    {
        $actor = $this->auth->requirePermission(Permission::RESERVATION_STAFF);
        $this->assertReservationSector($this->context);
        $input = $this->memberInput($request);
        try {
            $id = $this->booking->createTeamMember($actor, $input);
        } catch (ValidationException $exception) {
            return $this->createView($actor, $input, $exception->errors(), 422);
        }
        $this->session->put('status', $input['name'] . ' fait partie de l\'équipe. Réglez ses horaires ci-dessous (ou laissez vide : il suit les horaires du salon).');

        return Response::redirect('/reservation/equipe/' . $id . '/disponibilites');
    }

    /** Fiche d'une personne : informations, prestations, horaires et absences. */
    public function availabilityForm(Request $request): Response
    {
        $actor = $this->auth->requirePermission(Permission::RESERVATION_STAFF);
        $this->assertReservationSector($this->context);

        return $this->profile($actor, $this->id($request), [], []);
    }

    public function update(Request $request): Response
    {
        $actor = $this->auth->requirePermission(Permission::RESERVATION_STAFF);
        $id = $this->id($request);
        $input = $this->memberInput($request) + ['is_active' => $request->input('is_active') !== null ? '1' : ''];
        try {
            $this->booking->updateTeamMember($actor, $id, $input);
        } catch (ValidationException $exception) {
            return $this->profile($actor, $id, $input, $exception->errors(), 422);
        }
        $this->session->put('status', 'Fiche mise à jour.');

        return Response::redirect('/reservation/equipe/' . $id . '/disponibilites');
    }

    public function saveAvailability(Request $request): Response
    {
        $actor = $this->auth->requirePermission(Permission::RESERVATION_STAFF);
        $this->assertReservationSector($this->context);
        $id = $this->id($request);
        $slots = [];
        for ($day = 1; $day <= 7; ++$day) {
            $start = trim((string) $request->input('day_' . $day . '_start', ''));
            $end = trim((string) $request->input('day_' . $day . '_end', ''));
            if ($start !== '' && $end !== '') {
                $slots[] = ['weekday' => $day, 'starts_at' => $start . ':00', 'ends_at' => $end . ':00'];
            }
        }
        $this->booking->saveAvailability($actor, $id, $slots);
        $this->session->put('status', 'Horaires enregistrés.');

        return Response::redirect('/reservation/equipe');
    }

    public function addAbsence(Request $request): Response
    {
        $actor = $this->auth->requirePermission(Permission::RESERVATION_STAFF);
        $id = $this->id($request);
        $input = [
            'starts_on' => (string) $request->input('starts_on', ''),
            'ends_on' => (string) $request->input('ends_on', ''),
            'reason' => (string) $request->input('reason', ''),
        ];
        try {
            $this->booking->addAbsence($actor, $id, $input);
        } catch (ValidationException $exception) {
            return $this->profile($actor, $id, $input, $exception->errors(), 422);
        }
        $this->session->put('status', 'Absence enregistrée : aucun rendez-vous en ligne ne lui sera attribué ces jours-là.');

        return Response::redirect('/reservation/equipe/' . $id . '/disponibilites#absences');
    }

    public function deleteAbsence(Request $request): Response
    {
        $actor = $this->auth->requirePermission(Permission::RESERVATION_STAFF);
        $id = $this->id($request);
        $this->booking->deleteAbsence($actor, (int) $request->attribute('absence', '0'));
        $this->session->put('status', 'Absence retirée.');

        return Response::redirect('/reservation/equipe/' . $id . '/disponibilites#absences');
    }

    /**
     * @param array<string, mixed> $old
     * @param array<string, string> $errors
     */
    private function createView(User $actor, array $old, array $errors, int $status = 200): Response
    {
        return $this->renderAppArea('tenant/reservation/equipe/form', [
            'title' => 'Ajouter une personne',
            'navTitle' => 'Équipe',
            'current' => 'reservation-equipe',
            'old' => $old,
            'errors' => $errors,
            'prestations' => $this->booking->listPrestations($actor),
        ], $actor, $status);
    }

    /**
     * @param array<string, mixed> $old
     * @param array<string, string> $errors
     */
    private function profile(User $actor, int $id, array $old, array $errors, int $status = 200): Response
    {
        $member = $this->booking->findTeamMember($actor, $id);

        return $this->renderAppArea('tenant/reservation/equipe/availability', [
            'title' => $member->name() . ' — Équipe',
            'navTitle' => 'Équipe',
            'current' => 'reservation-equipe',
            'member' => $member,
            'prestations' => $this->booking->listPrestations($actor),
            'slotsByDay' => $this->slotsByWeekday($this->booking->availabilityForStaff($actor, $id)),
            'absences' => $this->booking->absences($actor, $id),
            'old' => $old,
            'errors' => $errors,
        ], $actor, $status);
    }

    /**
     * @return array<string, mixed>
     */
    private function memberInput(Request $request): array
    {
        $services = $request->input('service_ids');

        return [
            'name' => (string) $request->input('name', ''),
            'role_label' => (string) $request->input('role_label', ''),
            'phone' => (string) $request->input('phone', ''),
            'service_ids' => is_array($services) ? $services : [],
        ];
    }

    private function id(Request $request): int
    {
        $raw = (string) $request->attribute('id', '');
        if (!ctype_digit($raw) || (int) $raw < 1) {
            throw new HttpException(404, 'Personnel introuvable.');
        }

        return (int) $raw;
    }

    /**
     * @param list<array{weekday: int, starts_at: string, ends_at: string}> $slots
     * @return array<int, array{start: string, end: string}>
     */
    private function slotsByWeekday(array $slots): array
    {
        $byDay = [];
        foreach ($slots as $slot) {
            $day = (int) ($slot['weekday'] ?? 0);
            if ($day >= 1 && $day <= 7) {
                $byDay[$day] = ['start' => substr((string) $slot['starts_at'], 0, 5), 'end' => substr((string) $slot['ends_at'], 0, 5)];
            }
        }

        return $byDay;
    }
}
