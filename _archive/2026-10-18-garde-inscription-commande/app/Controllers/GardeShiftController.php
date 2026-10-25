<?php

declare(strict_types=1);

namespace Sesam\Controllers;

use Sesam\Controllers\Concerns\RendersAppArea;
use Sesam\Controllers\Concerns\RequiresGardeSector;
use Sesam\Core\Auth\Permission;
use Sesam\Core\HttpException;
use Sesam\Core\Request\Request;
use Sesam\Core\Response\Response;
use Sesam\Core\Response\ViewRenderer;
use Sesam\Core\Security\Csrf;
use Sesam\Core\Session\Session;
use Sesam\Core\Tenant\TenantContext;
use Sesam\Core\Validation\ValidationException;
use Sesam\Services\AuthService;
use Sesam\Services\Garde\DutyService;
use Sesam\Services\NotificationService;

final class GardeShiftController extends Controller
{
    use RendersAppArea;
    use RequiresGardeSector;

    public function __construct(
        ViewRenderer $views,
        private readonly Csrf $csrf,
        private readonly Session $session,
        private readonly AuthService $auth,
        private readonly TenantContext $context,
        private readonly DutyService $garde,
        private readonly NotificationService $notifications,
    ) {
        parent::__construct($views);
    }

    public function index(Request $request): Response
    {
        $actor = $this->auth->requirePermission(Permission::GARDE_VIEW);
        $this->assertGardeSector($this->context);
        $month = (string) $request->input('mois', gmdate('Y-m'));

        return $this->renderAppArea('tenant/garde/calendrier/index', [
            'title' => 'Calendrier de garde',
            'navTitle' => 'Calendrier',
            'current' => 'garde-calendrier',
            'calendar' => $this->garde->calendar($actor, $month),
            'canManage' => $actor->can(Permission::GARDE_SHIFTS),
        ], $actor);
    }

    public function createForm(Request $request): Response
    {
        $actor = $this->auth->requirePermission(Permission::GARDE_SHIFTS);
        $this->assertGardeSector($this->context);
        $month = (string) $request->input('mois', gmdate('Y-m'));
        $date = (string) $request->input('date', gmdate('Y-m-d'));
        $calendar = $this->garde->calendar($actor, $month);

        return $this->renderAppArea('tenant/garde/calendrier/form', [
            'title' => 'Planifier une garde',
            'navTitle' => 'Nouvelle garde',
            'current' => 'garde-calendrier',
            'staff' => $calendar['staff'],
            'old' => [
                'staff_id' => '',
                'shift_date' => $date,
                'note' => '',
            ],
            'errors' => [],
        ], $actor);
    }

    public function store(Request $request): Response
    {
        $actor = $this->auth->requirePermission(Permission::GARDE_SHIFTS);
        $this->assertGardeSector($this->context);
        $input = [
            'staff_id' => $request->input('staff_id'),
            'shift_date' => $request->input('shift_date'),
            'note' => $request->input('note'),
        ];
        try {
            $this->garde->createShift($actor, $input);
        } catch (ValidationException $exception) {
            $month = substr((string) ($input['shift_date'] ?? ''), 0, 7);
            $calendar = $this->garde->calendar($actor, $month !== '' ? $month : gmdate('Y-m'));

            return $this->renderAppArea('tenant/garde/calendrier/form', [
                'title' => 'Planifier une garde',
                'navTitle' => 'Nouvelle garde',
                'current' => 'garde-calendrier',
                'staff' => $calendar['staff'],
                'old' => [
                    'staff_id' => (string) ($input['staff_id'] ?? ''),
                    'shift_date' => (string) ($input['shift_date'] ?? ''),
                    'note' => (string) ($input['note'] ?? ''),
                ],
                'errors' => $exception->errors(),
            ], $actor);
        }
        $this->session->put('status', 'Tour de garde enregistré.');
        $month = substr((string) $input['shift_date'], 0, 7);

        return Response::redirect('/garde/calendrier' . ($month !== '' ? '?mois=' . rawurlencode($month) : ''));
    }

    public function destroy(Request $request): Response
    {
        $actor = $this->auth->requirePermission(Permission::GARDE_SHIFTS);
        $this->assertGardeSector($this->context);
        $this->garde->deleteShift($actor, $this->id($request));
        $this->session->put('status', 'Garde retirée du calendrier.');
        $month = (string) $request->input('mois', gmdate('Y-m'));

        return Response::redirect('/garde/calendrier?mois=' . rawurlencode($month));
    }

    private function id(Request $request): int
    {
        $raw = (string) $request->attribute('id', '');
        if (!ctype_digit($raw) || (int) $raw < 1) {
            throw new HttpException(404, 'Garde introuvable.');
        }

        return (int) $raw;
    }
}
