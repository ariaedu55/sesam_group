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
use Sesam\Models\ReservationPrestation;
use Sesam\Models\User;
use Sesam\Services\AuthService;
use Sesam\Services\NotificationService;
use Sesam\Services\Reservation\BookingService;

final class ReservationPrestationController extends Controller
{
    use RendersAppArea;
    use RequiresReservationSector;

    private const FIELDS = ['name', 'description', 'category', 'duration_minutes', 'price_minor', 'deposit_minor'];

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

        return $this->renderAppArea('tenant/reservation/prestations/index', [
            'title' => 'Prestations',
            'navTitle' => 'Prestations',
            'current' => 'reservation-prestations',
            'items' => $this->booking->listPrestations($actor),
            'canManage' => $actor->can(Permission::RESERVATION_SERVICES),
        ], $actor);
    }

    public function createForm(Request $request): Response
    {
        $actor = $this->auth->requirePermission(Permission::RESERVATION_SERVICES);
        $this->assertReservationSector($this->context);

        return $this->form($actor, null, ['duration_minutes' => '30', 'deposit_minor' => '0', 'is_active' => '1']);
    }

    public function store(Request $request): Response
    {
        $actor = $this->auth->requirePermission(Permission::RESERVATION_SERVICES);
        $this->assertReservationSector($this->context);
        $input = $this->input($request);
        try {
            $this->booking->createPrestation($actor, $input, $request->file('image'));
        } catch (ValidationException $exception) {
            return $this->form($actor, null, $input, $exception->errors(), 422);
        }
        $this->session->put('status', 'Prestation enregistrée. Elle apparaît sur votre page de réservation.');

        return Response::redirect($request->input('encore') !== null ? '/reservation/prestations/nouveau' : '/reservation/prestations');
    }

    public function editForm(Request $request): Response
    {
        $actor = $this->auth->requirePermission(Permission::RESERVATION_SERVICES);
        $prestation = $this->booking->findPrestation($actor, $this->id($request));

        return $this->form($actor, $prestation, [
            'name' => $prestation->name(),
            'description' => $prestation->description(),
            'category' => $prestation->category(),
            'duration_minutes' => (string) $prestation->durationMinutes(),
            'price_minor' => (string) $prestation->priceMinor(),
            'deposit_minor' => (string) $prestation->depositMinor(),
            'is_active' => $prestation->isActive() ? '1' : '',
        ]);
    }

    public function update(Request $request): Response
    {
        $actor = $this->auth->requirePermission(Permission::RESERVATION_SERVICES);
        $id = $this->id($request);
        $input = $this->input($request) + ['is_active' => $request->input('is_active') !== null ? '1' : ''];
        try {
            $this->booking->updatePrestation($actor, $id, $input, $request->file('image'), $request->input('remove_image') !== null);
        } catch (ValidationException $exception) {
            return $this->form($actor, $this->booking->findPrestation($actor, $id), $input, $exception->errors(), 422);
        }
        $this->session->put('status', 'Prestation mise à jour.');

        return Response::redirect('/reservation/prestations');
    }

    public function toggle(Request $request): Response
    {
        $actor = $this->auth->requirePermission(Permission::RESERVATION_SERVICES);
        $active = $this->booking->togglePrestation($actor, $this->id($request));
        $this->session->put('status', $active ? 'Prestation de nouveau proposée.' : 'Prestation retirée de la réservation en ligne.');

        return Response::redirect('/reservation/prestations');
    }

    /**
     * @param array<string, mixed> $old
     * @param array<string, string> $errors
     */
    private function form(User $actor, ?ReservationPrestation $prestation, array $old, array $errors = [], int $status = 200): Response
    {
        return $this->renderAppArea('tenant/reservation/prestations/form', [
            'title' => $prestation === null ? 'Nouvelle prestation' : 'Modifier ' . $prestation->name(),
            'navTitle' => 'Prestation',
            'current' => 'reservation-prestations',
            'prestation' => $prestation,
            'categories' => $this->booking->prestationCategories($actor),
            'old' => $old,
            'errors' => $errors,
        ], $actor, $status);
    }

    /**
     * @return array<string, string>
     */
    private function input(Request $request): array
    {
        $input = [];
        foreach (self::FIELDS as $field) {
            $input[$field] = (string) $request->input($field, '');
        }

        return $input;
    }

    private function id(Request $request): int
    {
        $id = (int) $request->attribute('id', '0');
        if ($id < 1) {
            throw new HttpException(404, 'Prestation introuvable.');
        }

        return $id;
    }
}
