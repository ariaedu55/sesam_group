<?php

declare(strict_types=1);

namespace Sesam\Controllers;

use Sesam\Controllers\Concerns\RendersAppArea;
use Sesam\Controllers\Concerns\RequiresReservationSector;
use Sesam\Core\Auth\Permission;
use Sesam\Core\Boutique\WhatsApp;
use Sesam\Core\HttpException;
use Sesam\Core\Request\Request;
use Sesam\Core\Response\Response;
use Sesam\Core\Response\ViewRenderer;
use Sesam\Core\Security\Csrf;
use Sesam\Core\Session\Session;
use Sesam\Core\Tenant\TenantContext;
use Sesam\Core\Validation\ValidationException;
use Sesam\Models\Tenant;
use Sesam\Models\User;
use Sesam\Services\AuthService;
use Sesam\Services\NotificationService;
use Sesam\Services\Reservation\BookingService;
use Sesam\Services\Reservation\SalonService;

/**
 * Agenda du salon et page « Mon salon » (présentation, horaires, fermetures, règles de réservation).
 */
final class ReservationSalonController extends Controller
{
    use RendersAppArea;
    use RequiresReservationSector;

    private const TEXT_FIELDS = ['display_name', 'whatsapp_phone', 'tagline', 'description', 'address', 'cancellation_policy', 'theme',
        'slot_step_minutes', 'min_notice_hours', 'max_advance_days', 'buffer_minutes'];

    public function __construct(
        ViewRenderer $views,
        private readonly Csrf $csrf,
        private readonly Session $session,
        private readonly AuthService $auth,
        private readonly TenantContext $context,
        private readonly BookingService $booking,
        private readonly SalonService $salon,
        private readonly NotificationService $notifications,
    ) {
        parent::__construct($views);
    }

    public function agenda(Request $request): Response
    {
        $actor = $this->auth->requirePermission(Permission::RESERVATION_VIEW);
        $this->assertReservationSector($this->context);
        $staff = (int) $request->input('personne', '0');

        return $this->renderAppArea('tenant/reservation/agenda', [
            'title' => 'Agenda',
            'navTitle' => 'Agenda',
            'current' => 'reservation-agenda',
            'agenda' => $this->booking->agenda(
                $actor,
                (string) $request->input('date', gmdate('Y-m-d')),
                (string) $request->input('vue', 'jour'),
                $staff > 0 ? $staff : null,
            ),
            'canManage' => $actor->can(Permission::RESERVATION_APPOINTMENTS),
        ], $actor);
    }

    public function form(Request $request): Response
    {
        $actor = $this->auth->requirePermission(Permission::RESERVATION_VIEW);
        $this->assertReservationSector($this->context);
        $settings = $this->salon->settingsFor($actor);
        $tenant = $this->context->tenant();
        $old = [
            'display_name' => $tenant instanceof Tenant ? $tenant->displayName() : '',
            'whatsapp_phone' => WhatsApp::display($settings->whatsappPhone()),
            'tagline' => $settings->tagline(),
            'description' => $settings->description(),
            'address' => $settings->address(),
            'cancellation_policy' => $settings->cancellationPolicy(),
            'theme' => $settings->theme(),
            'slot_step_minutes' => (string) $settings->slotStepMinutes(),
            'min_notice_hours' => (string) $settings->minNoticeHours(),
            'max_advance_days' => (string) $settings->maxAdvanceDays(),
            'buffer_minutes' => (string) $settings->bufferMinutes(),
            'is_open' => $settings->isOpen() ? '1' : '',
            'auto_confirm' => $settings->autoConfirm() ? '1' : '',
        ];
        $hours = $settings->hours();
        $default = $hours === [];
        for ($day = 1; $day <= 7; ++$day) {
            $ranges = $default ? ($day <= 6 ? [['09:00', '19:00']] : []) : array_map(static fn (array $r): array => [substr($r[0], 0, 5), substr($r[1], 0, 5)], $hours[$day] ?? []);
            $old['day_' . $day . '_open'] = $ranges !== [] ? '1' : '';
            $old['day_' . $day . '_a_from'] = $ranges[0][0] ?? '';
            $old['day_' . $day . '_a_to'] = $ranges[0][1] ?? '';
            $old['day_' . $day . '_b_from'] = $ranges[1][0] ?? '';
            $old['day_' . $day . '_b_to'] = $ranges[1][1] ?? '';
        }

        return $this->view($actor, $old, [], 200, $default);
    }

    public function update(Request $request): Response
    {
        $actor = $this->auth->requirePermission(Permission::RESERVATION_SETTINGS);
        $this->assertReservationSector($this->context);
        $input = [];
        foreach (self::TEXT_FIELDS as $field) {
            $input[$field] = (string) $request->input($field, '');
        }
        foreach (['is_open', 'auto_confirm', 'remove_logo', 'remove_cover'] as $flag) {
            $input[$flag] = $request->input($flag) !== null ? '1' : '';
        }
        for ($day = 1; $day <= 7; ++$day) {
            $input['day_' . $day . '_open'] = $request->input('day_' . $day . '_open') !== null ? '1' : '';
            foreach (['a_from', 'a_to', 'b_from', 'b_to'] as $part) {
                $input['day_' . $day . '_' . $part] = (string) $request->input('day_' . $day . '_' . $part, '');
            }
        }
        try {
            $this->salon->save($actor, $input, $request->file('logo'), $request->file('cover'));
        } catch (ValidationException $exception) {
            $errors = $exception->errors();
            if (isset($errors['image'])) {
                $errors['logo'] = $errors['image'];
            }

            return $this->view($actor, $input, $errors, 422);
        }
        $this->session->put('status', 'Votre salon est à jour.');

        return Response::redirect('/reservation/salon');
    }

    public function addClosure(Request $request): Response
    {
        $actor = $this->auth->requirePermission(Permission::RESERVATION_SETTINGS);
        try {
            $this->salon->addClosure($actor, [
                'starts_on' => (string) $request->input('starts_on', ''),
                'ends_on' => (string) $request->input('ends_on', ''),
                'reason' => (string) $request->input('reason', ''),
            ]);
        } catch (ValidationException $exception) {
            $this->session->put('status', implode(' ', $exception->errors()));

            return Response::redirect('/reservation/salon#fermetures');
        }
        $this->session->put('status', 'Fermeture enregistrée : personne ne pourra réserver ces jours-là.');

        return Response::redirect('/reservation/salon#fermetures');
    }

    public function deleteClosure(Request $request): Response
    {
        $actor = $this->auth->requirePermission(Permission::RESERVATION_SETTINGS);
        $id = (int) $request->attribute('id', '0');
        if ($id < 1) {
            throw new HttpException(404, 'Fermeture introuvable.');
        }
        $this->salon->deleteClosure($actor, $id);
        $this->session->put('status', 'Fermeture retirée.');

        return Response::redirect('/reservation/salon#fermetures');
    }

    /**
     * @param array<string, string> $old
     * @param array<string, string> $errors
     */
    private function view(User $actor, array $old, array $errors, int $status = 200, bool $suggestedHours = false): Response
    {
        return $this->renderAppArea('tenant/reservation/salon', [
            'title' => 'Mon salon',
            'navTitle' => 'Mon salon',
            'current' => 'reservation-salon',
            'settings' => $this->salon->settingsFor($actor),
            'closures' => $this->salon->closures($actor),
            'publicUrl' => $this->salon->publicUrlFor($actor),
            'old' => $old,
            'errors' => $errors,
            'suggestedHours' => $suggestedHours,
            'canUpdate' => $actor->can(Permission::RESERVATION_SETTINGS),
        ], $actor, $status);
    }
}
