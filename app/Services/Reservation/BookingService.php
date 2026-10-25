<?php

declare(strict_types=1);

namespace Sesam\Services\Reservation;

use Sesam\Core\Auth\Permission;
use Sesam\Core\Boutique\Money;
use Sesam\Core\Boutique\WhatsApp;
use Sesam\Core\Crm\ClientStatus;
use Sesam\Core\Database\Connection;
use Sesam\Core\HttpException;
use Sesam\Core\Logger\Logger;
use Sesam\Core\Messaging\MessageChannel;
use Sesam\Core\Reservation\AppointmentStatus;
use Sesam\Core\Tenant\Sector;
use Sesam\Core\Tenant\TenantContext;
use Sesam\Core\Validation\ValidationException;
use Sesam\Models\ReservationAppointment;
use Sesam\Models\ReservationPrestation;
use Sesam\Models\ReservationTeamMember;
use Sesam\Models\Tenant;
use Sesam\Models\User;
use Sesam\Repositories\ClientActivityRepository;
use Sesam\Repositories\ClientRepository;
use Sesam\Repositories\ReservationAppointmentRepository;
use Sesam\Repositories\ReservationPrestationRepository;
use Sesam\Repositories\ReservationSettingsRepository;
use Sesam\Repositories\ReservationTeamRepository;
use Sesam\Services\MessagingService;
use Sesam\Services\PaymentService;
use Sesam\Services\Service;

/**
 * Module Réservation : prestations, équipe, rendez-vous, agenda et réservation en ligne.
 */
final class BookingService extends Service
{
    public const PER_PAGE = 25;
    private const WEEKDAYS = [1 => 'Lundi', 2 => 'Mardi', 3 => 'Mercredi', 4 => 'Jeudi', 5 => 'Vendredi', 6 => 'Samedi', 7 => 'Dimanche'];

    private readonly ReservationPrestationRepository $prestations;
    private readonly ReservationTeamRepository $team;
    private readonly ReservationAppointmentRepository $appointments;
    private readonly ReservationSettingsRepository $settings;
    private readonly ClientRepository $clients;
    private readonly \Sesam\Repositories\PaymentRepository $paymentRecords;
    private readonly ClientActivityRepository $activities;
    private readonly AvailabilityService $availability;

    /**
     * @param array{host: string, port: string, name: string, user: string, password: string} $database
     */
    private ?\Sesam\Services\Storage\ImageUploadService $images;

    /** Photos des prestations (branché par le Bootstrap). */
    public function useImages(\Sesam\Services\Storage\ImageUploadService $images): void
    {
        $this->images = $images;
    }

    public function __construct(
        array $database,
        private readonly TenantContext $context,
        private readonly ?MessagingService $messaging = null,
        private readonly ?PaymentService $payments = null,
        private readonly ?Logger $logger = null,
    ) {
        $pdo = Connection::make($database);
        $this->prestations = new ReservationPrestationRepository($pdo);
        $this->team = new ReservationTeamRepository($pdo);
        $this->appointments = new ReservationAppointmentRepository($pdo);
        $this->settings = new ReservationSettingsRepository($pdo);
        $this->clients = new ClientRepository($pdo);
        $this->activities = new ClientActivityRepository($pdo);
        $this->availability = new AvailabilityService($this->settings, $this->team, $this->appointments);
        $this->images = null;
        $this->paymentRecords = new \Sesam\Repositories\PaymentRepository($pdo);
    }

    public static function weekdayLabel(int $weekday): string
    {
        return self::WEEKDAYS[$weekday] ?? '';
    }

    // ---------------------------------------------------------------- Prestations

    /**
     * @return list<ReservationPrestation>
     */
    public function listPrestations(User $actor): array
    {
        $this->assertReservation($actor, Permission::RESERVATION_VIEW);

        return $this->prestations->listAll($this->tenantId($actor));
    }

    /**
     * @return list<string>
     */
    public function prestationCategories(User $actor): array
    {
        $this->assertReservation($actor, Permission::RESERVATION_VIEW);

        return $this->prestations->categories($this->tenantId($actor));
    }

    public function findPrestation(User $actor, int $id): ReservationPrestation
    {
        $this->assertReservation($actor, Permission::RESERVATION_VIEW);
        $prestation = $this->prestations->findById($id, $this->tenantId($actor));
        if ($prestation === null) {
            throw new HttpException(404, 'Prestation introuvable.');
        }

        return $prestation;
    }

    /**
     * @param array<string, mixed> $input
     */
    public function createPrestation(User $actor, array $input, ?array $image = null): int
    {
        $tenantId = $this->tenantId($actor);
        $this->assertReservation($actor, Permission::RESERVATION_SERVICES);

        $data = $this->validatedPrestation($input);
        $path = $this->storePrestationImage($tenantId, $image);
        $id = $this->prestations->insert($tenantId, $data + ['category_id' => null]);
        if ($path !== null) {
            $this->prestations->updateImage($id, $tenantId, $path);
        }

        return $id;
    }

    /**
     * Photo envoyée avec le formulaire (facultative) : enregistrée en WebP compressé.
     *
     * @param array<string, mixed>|null $image
     */
    private function storePrestationImage(int $tenantId, ?array $image): ?string
    {
        if ($this->images === null || !is_array($image) || (int) ($image['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            return null;
        }
        try {
            return $this->images->store($image, 'salon/' . $tenantId);
        } catch (ValidationException $exception) {
            throw new ValidationException(['image' => $exception->errors()['image'] ?? 'Photo invalide.']);
        }
    }

    /**
     * Photo d'une prestation pour la page publique.
     *
     * @return array{contents: string, mime: string}
     */
    public function prestationImage(Tenant $tenant, int $id): array
    {
        $prestation = $this->prestations->findById($id, $tenant->id());
        if ($this->images === null || $prestation === null || $prestation->imagePath() === null) {
            throw new HttpException(404, 'Image introuvable.');
        }

        return $this->images->read($prestation->imagePath());
    }

    /**
     * @param array<string, mixed> $input
     */
    public function updatePrestation(User $actor, int $id, array $input, ?array $image = null, bool $removeImage = false): void
    {
        $tenantId = $this->tenantId($actor);
        $this->assertReservation($actor, Permission::RESERVATION_SERVICES);
        $current = $this->prestations->findById($id, $tenantId);
        if ($current === null) {
            throw new HttpException(404, 'Prestation introuvable.');
        }
        $data = $this->validatedPrestation($input);
        $path = $this->storePrestationImage($tenantId, $image);
        $this->prestations->update($id, $tenantId, $data);
        if ($path !== null || $removeImage) {
            $this->prestations->updateImage($id, $tenantId, $path);
            $this->images?->delete($current->imagePath());
        }
    }

    public function togglePrestation(User $actor, int $id): bool
    {
        $tenantId = $this->tenantId($actor);
        $this->assertReservation($actor, Permission::RESERVATION_SERVICES);
        $prestation = $this->prestations->findById($id, $tenantId);
        if ($prestation === null) {
            throw new HttpException(404, 'Prestation introuvable.');
        }
        $this->prestations->setActive($id, $tenantId, !$prestation->isActive());

        return !$prestation->isActive();
    }

    // ---------------------------------------------------------------- Équipe

    /**
     * @return list<ReservationTeamMember>
     */
    public function listTeam(User $actor): array
    {
        $this->assertReservation($actor, Permission::RESERVATION_VIEW);

        return $this->team->listAll($this->tenantId($actor));
    }

    /**
     * @param array<string, mixed>|string $input
     */
    public function createTeamMember(User $actor, array|string $input): int
    {
        $tenantId = $this->tenantId($actor);
        $this->assertReservation($actor, Permission::RESERVATION_STAFF);
        $data = $this->validatedMember(is_array($input) ? $input : ['name' => $input]);
        $id = $this->team->insert($tenantId, $data['name'], $data['role'], $data['phone']);
        if (is_array($input)) {
            $this->team->replaceServices($tenantId, $id, $this->validServiceIds($tenantId, $input['service_ids'] ?? []));
        }

        return $id;
    }

    /**
     * @param array<string, mixed> $input
     */
    public function updateTeamMember(User $actor, int $id, array $input): void
    {
        $tenantId = $this->tenantId($actor);
        $this->assertReservation($actor, Permission::RESERVATION_STAFF);
        if ($this->team->findById($id, $tenantId) === null) {
            throw new HttpException(404, 'Personnel introuvable.');
        }
        $data = $this->validatedMember($input);
        $this->team->update($id, $tenantId, $data['name'], $data['role'], $data['phone'], !empty($input['is_active']));
        $this->team->replaceServices($tenantId, $id, $this->validServiceIds($tenantId, $input['service_ids'] ?? []));
    }

    public function findTeamMember(User $actor, int $id): ReservationTeamMember
    {
        $tenantId = $this->tenantId($actor);
        $this->assertReservation($actor, Permission::RESERVATION_VIEW);
        $member = $this->team->findById($id, $tenantId);
        if ($member === null) {
            throw new HttpException(404, 'Personnel introuvable.');
        }

        return $member;
    }

    /**
     * @return list<array{weekday: int, starts_at: string, ends_at: string}>
     */
    public function availabilityForStaff(User $actor, int $staffId): array
    {
        $tenantId = $this->tenantId($actor);
        $this->assertReservation($actor, Permission::RESERVATION_STAFF);
        if ($this->team->findById($staffId, $tenantId) === null) {
            throw new HttpException(404, 'Personnel introuvable.');
        }

        return $this->team->availability($tenantId, $staffId);
    }

    public function saveAvailability(User $actor, int $staffId, array $slots): void
    {
        $tenantId = $this->tenantId($actor);
        $this->assertReservation($actor, Permission::RESERVATION_STAFF);
        if ($this->team->findById($staffId, $tenantId) === null) {
            throw new HttpException(404, 'Personnel introuvable.');
        }
        $clean = [];
        foreach ($slots as $slot) {
            $weekday = (int) ($slot['weekday'] ?? -1);
            $start = (string) ($slot['starts_at'] ?? '');
            $end = (string) ($slot['ends_at'] ?? '');
            if ($weekday < 1 || $weekday > 7 || $start === '' || $end === '' || $start >= $end) {
                continue;
            }
            $clean[] = ['weekday' => $weekday, 'starts_at' => $start, 'ends_at' => $end];
        }
        $this->team->replaceAvailability($tenantId, $staffId, $clean);
    }

    /**
     * @return list<array{id: int, staff_id: ?int, staff_name: string, starts_on: string, ends_on: string, reason: string}>
     */
    public function absences(User $actor, int $staffId): array
    {
        $this->findTeamMember($actor, $staffId);

        return $this->settings->closures($this->tenantId($actor), gmdate('Y-m-d'), $staffId);
    }

    /**
     * @param array<string, mixed> $input
     */
    public function addAbsence(User $actor, int $staffId, array $input): void
    {
        $tenantId = $this->tenantId($actor);
        $this->assertReservation($actor, Permission::RESERVATION_STAFF);
        $this->findTeamMember($actor, $staffId);
        [$from, $to, $reason] = $this->validatedPeriod($input);
        $this->settings->insertClosure($tenantId, $staffId, $from, $to, $reason);
    }

    public function deleteAbsence(User $actor, int $closureId): void
    {
        $tenantId = $this->tenantId($actor);
        $this->assertReservation($actor, Permission::RESERVATION_STAFF);
        if (!$this->settings->deleteClosure($tenantId, $closureId)) {
            throw new HttpException(404, 'Absence introuvable.');
        }
    }

    // ---------------------------------------------------------------- Rendez-vous

    /**
     * @param array{status?: string, period?: string, q?: string, staff_id?: int} $filters
     * @return array{appointments: list<ReservationAppointment>, total: int, page: int, pages: int, counts: array<string, int>}
     */
    public function paginateAppointments(User $actor, int $page, array $filters = []): array
    {
        $tenantId = $this->tenantId($actor);
        $this->assertReservation($actor, Permission::RESERVATION_VIEW);
        $period = (string) ($filters['period'] ?? '');
        $today = gmdate('Y-m-d') . ' 00:00:00';
        $range = match ($period) {
            'aujourdhui' => ['from' => $today, 'to' => gmdate('Y-m-d', strtotime('+1 day')) . ' 00:00:00'],
            'semaine' => ['from' => $today, 'to' => gmdate('Y-m-d', strtotime('+7 days')) . ' 00:00:00'],
            'a-venir' => ['from' => gmdate('Y-m-d H:i:s')],
            'passes' => ['to' => gmdate('Y-m-d H:i:s')],
            default => [],
        };
        $query = [
            'status' => (string) ($filters['status'] ?? ''),
            'q' => mb_substr(trim((string) ($filters['q'] ?? '')), 0, 80),
            'staff_id' => (int) ($filters['staff_id'] ?? 0),
        ] + $range;
        $total = $this->appointments->countSearch($tenantId, $query);
        $pages = max(1, (int) ceil($total / self::PER_PAGE));
        $page = min(max(1, $page), $pages);
        $ascending = in_array($period, ['aujourdhui', 'semaine', 'a-venir'], true);

        return [
            'appointments' => $this->appointments->search($tenantId, $query, self::PER_PAGE, ($page - 1) * self::PER_PAGE, $ascending),
            'total' => $total,
            'page' => $page,
            'pages' => $pages,
            'counts' => $this->appointments->countByStatus($tenantId, '2000-01-01 00:00:00', '2100-01-01 00:00:00'),
        ];
    }

    /**
     * Agenda d'un jour ou d'une semaine : rendez-vous groupés par jour et par personne.
     *
     * @return array<string, mixed>
     */
    public function agenda(User $actor, string $date, string $view, ?int $staffId): array
    {
        $tenantId = $this->tenantId($actor);
        $this->assertReservation($actor, Permission::RESERVATION_VIEW);
        $day = strtotime($date . ' 00:00:00 UTC');
        if ($day === false || gmdate('Y-m-d', $day) !== $date) {
            $day = (int) strtotime(gmdate('Y-m-d') . ' 00:00:00 UTC');
        }
        $view = $view === 'semaine' ? 'semaine' : 'jour';
        if ($view === 'semaine') {
            $day -= ((int) gmdate('N', $day) - 1) * 86400;
        }
        $days = $view === 'semaine' ? 7 : 1;
        $from = gmdate('Y-m-d H:i:s', $day);
        $to = gmdate('Y-m-d H:i:s', $day + $days * 86400);
        $settings = $this->settings->find($tenantId);
        $team = $this->team->listActive($tenantId);
        $byDay = [];
        foreach ($this->appointments->listBetween($tenantId, $from, $to) as $appointment) {
            if ($staffId !== null && $appointment->staffId() !== $staffId) {
                continue;
            }
            $byDay[substr($appointment->startsAt(), 0, 10)][] = $appointment;
        }
        [$open, $close] = $this->agendaBounds($settings->hours(), $byDay);
        $closures = [];
        foreach ($this->settings->closures($tenantId, gmdate('Y-m-d', $day)) as $closure) {
            if ($closure['starts_on'] <= gmdate('Y-m-d', $day + ($days - 1) * 86400)) {
                $closures[] = $closure;
            }
        }

        return [
            'view' => $view,
            'date' => gmdate('Y-m-d', $day),
            'days' => array_map(static fn (int $i): string => gmdate('Y-m-d', $day + $i * 86400), range(0, $days - 1)),
            'prev' => gmdate('Y-m-d', $day - $days * 86400),
            'next' => gmdate('Y-m-d', $day + $days * 86400),
            'team' => $team,
            'staffId' => $staffId,
            'byDay' => $byDay,
            'open' => $open,
            'close' => $close,
            'closures' => $closures,
            'hours' => $settings->hours(),
        ];
    }

    public function findAppointment(User $actor, int $id): ReservationAppointment
    {
        $tenantId = $this->tenantId($actor);
        $this->assertReservation($actor, Permission::RESERVATION_VIEW);
        $appointment = $this->appointments->findById($id, $tenantId);
        if ($appointment === null) {
            throw new HttpException(404, 'Rendez-vous introuvable.');
        }

        return $appointment;
    }

    /**
     * @return array{appointment: ReservationAppointment, whatsapp: string, history: list<ReservationAppointment>}
     */
    public function appointmentDetail(User $actor, int $id): array
    {
        $appointment = $this->findAppointment($actor, $id);
        $tenant = $this->context->tenant();

        return [
            'appointment' => $appointment,
            'payment' => $appointment->paymentId() !== null ? $this->paymentRecords->findById($appointment->paymentId(), $this->tenantId($actor)) : null,
            'whatsapp' => $tenant instanceof Tenant ? $this->clientWhatsApp($tenant, $appointment) : '',
            'history' => array_values(array_filter(
                $this->appointments->listForClient($this->tenantId($actor), $appointment->clientId(), 6),
                static fn (ReservationAppointment $item): bool => $item->id() !== $id
            )),
        ];
    }

    /**
     * Rendez-vous saisi par le salon (téléphone, passage, message). Le salon peut forcer un horaire.
     *
     * @param array<string, mixed> $input
     */
    public function createAppointment(User $actor, array $input): int
    {
        $tenantId = $this->tenantId($actor);
        $this->assertReservation($actor, Permission::RESERVATION_APPOINTMENTS);
        $clientId = (int) ($input['client_id'] ?? 0);
        $serviceId = (int) ($input['service_id'] ?? 0);
        $staffRaw = $input['staff_id'] ?? null;
        $staffId = is_numeric($staffRaw) && (int) $staffRaw > 0 ? (int) $staffRaw : null;
        $notes = trim((string) ($input['notes'] ?? ''));

        $newName = trim((string) ($input['client_name'] ?? ''));
        $newPhone = trim((string) ($input['client_phone'] ?? ''));
        if ($clientId > 0 && $this->clients->findById($clientId, $tenantId) === null) {
            throw new ValidationException(['client_name' => 'Cette cliente n\'existe plus. Écrivez son nom et son numéro.']);
        }
        if ($clientId === 0) {
            // Nouvelle cliente : nom et numéro saisis à la main, la fiche est créée à l'enregistrement.
            $clientErrors = [];
            if ($newName === '' || mb_strlen($newName) > 120) {
                $clientErrors['client_name'] = $newName === '' ? 'Écrivez le nom de la cliente.' : 'Nom trop long (120 caractères max.).';
            }
            $digits = (string) preg_replace('/\D+/', '', $newPhone);
            if (strlen($digits) < 8 || strlen($digits) > 15 || mb_strlen($newPhone) > 40) {
                $clientErrors['client_phone'] = $newPhone === '' ? 'Indiquez le numéro de la cliente.' : 'Numéro invalide (8 chiffres au moins).';
            }
            if ($clientErrors !== []) {
                throw new ValidationException($clientErrors);
            }
        }
        $service = $this->prestations->findById($serviceId, $tenantId);
        if ($service === null || !$service->isActive()) {
            throw new ValidationException(['service_id' => 'Prestation introuvable.']);
        }
        if ($staffId !== null && $this->team->findById($staffId, $tenantId) === null) {
            throw new ValidationException(['staff_id' => 'Personnel introuvable.']);
        }
        $startsAt = $this->parseStartsAt(trim((string) ($input['starts_at'] ?? '')));
        $this->assertWithinAvailability($tenantId, $staffId, $startsAt, $service->durationMinutes());
        if ($this->appointments->hasOverlap($tenantId, $staffId, $startsAt, $service->durationMinutes(), null)) {
            throw new ValidationException(['starts_at' => 'Ce créneau est déjà réservé.']);
        }
        if ($clientId === 0) {
            $clientId = $this->findOrCreateClient($tenantId, $newName, $newPhone, 'Ajouté depuis l\'agenda');
        } else {
            // Cliente choisie dans les suggestions : on complète son numéro s'il manquait sur sa fiche.
            $known = $this->clients->findById($clientId, $tenantId);
            $digits = (string) preg_replace('/\D+/', '', $newPhone);
            if ($known !== null && $known->phone() === null && strlen($digits) >= 8 && mb_strlen($newPhone) <= 40) {
                $this->clients->update($clientId, $tenantId, [
                    'full_name' => $known->fullName(),
                    'email' => $known->email(),
                    'phone' => $newPhone,
                    'status' => $known->status(),
                ]);
            }
        }
        $status = !empty($input['confirm_now']) ? AppointmentStatus::CONFIRMED : AppointmentStatus::PENDING;
        $id = $this->appointments->insert($tenantId, [
            'client_id' => $clientId,
            'service_id' => $serviceId,
            'staff_id' => $staffId,
            'starts_at' => $startsAt,
            'duration_minutes' => $service->durationMinutes(),
            'price_minor' => $service->priceMinor(),
            'status' => $status,
            'source' => 'manual',
            'deposit_amount_minor' => $service->depositMinor(),
            'notes' => $notes !== '' ? mb_substr($notes, 0, 500) : null,
        ]);
        $this->activities->insert($tenantId, $clientId, $actor->id(), 'reservation', 'Rendez-vous ' . $service->name() . ' le ' . $this->human($startsAt));
        if ($status === AppointmentStatus::CONFIRMED) {
            $created = $this->appointments->findById($id, $tenantId);
            if ($created !== null) {
                $this->scheduleReminder($tenantId, $created);
            }
        }

        return $id;
    }

    public function confirm(User $actor, int $id): void
    {
        $this->changeStatus($actor, $id, AppointmentStatus::CONFIRMED);
    }

    public function cancel(User $actor, int $id): void
    {
        $this->changeStatus($actor, $id, AppointmentStatus::CANCELLED);
    }

    public function changeStatus(User $actor, int $id, string $status): ReservationAppointment
    {
        $tenantId = $this->tenantId($actor);
        $this->assertReservation($actor, Permission::RESERVATION_APPOINTMENTS);
        $appointment = $this->findAppointment($actor, $id);
        if (!AppointmentStatus::canMoveTo($appointment->status(), $status)) {
            $message = $appointment->status() === AppointmentStatus::CANCELLED
                ? 'Rendez-vous annulé.'
                : 'Ce rendez-vous ne peut pas passer à « ' . AppointmentStatus::label($status) . ' ».';
            throw new ValidationException(['status' => $message]);
        }
        if ($status === AppointmentStatus::PENDING
            && $this->appointments->hasOverlap($tenantId, $appointment->staffId(), $appointment->startsAt(), $appointment->durationMinutes(), $id)) {
            throw new ValidationException(['status' => 'Le créneau a été repris entre-temps. Reprogrammez ce rendez-vous.']);
        }
        $this->appointments->updateStatus($id, $tenantId, $status);
        if ($status === AppointmentStatus::CONFIRMED) {
            $this->scheduleReminder($tenantId, $appointment);
            if ($appointment->status() === AppointmentStatus::PENDING) {
                $this->tellClient($appointment, 'Votre rendez-vous ' . $appointment->serviceName() . ' du ' . $this->human($appointment->startsAt()) . ' est confirmé. À bientôt !');
            }
        } else {
            $this->messaging?->cancelReminders($tenantId, self::reminderRef($id));
            if ($status === AppointmentStatus::CANCELLED && in_array($appointment->status(), AppointmentStatus::active(), true)) {
                $this->tellClient($appointment, 'Votre rendez-vous ' . $appointment->serviceName() . ' du ' . $this->human($appointment->startsAt()) . ' est annulé. Écrivez-nous pour choisir un autre moment.');
            }
        }
        $updated = $this->appointments->findById($id, $tenantId);

        return $updated ?? $appointment;
    }

    public function togglePaid(User $actor, int $id): bool
    {
        $tenantId = $this->tenantId($actor);
        $this->assertReservation($actor, Permission::RESERVATION_APPOINTMENTS);
        $appointment = $this->findAppointment($actor, $id);
        $this->appointments->setPaid($id, $tenantId, !$appointment->isPaid());

        return !$appointment->isPaid();
    }

    /**
     * @param array<string, mixed> $input
     */
    public function reschedule(User $actor, int $id, array $input): void
    {
        $tenantId = $this->tenantId($actor);
        $this->assertReservation($actor, Permission::RESERVATION_APPOINTMENTS);
        $appointment = $this->findAppointment($actor, $id);
        if (!in_array($appointment->status(), AppointmentStatus::active(), true)) {
            throw new ValidationException(['starts_at' => 'Seul un rendez-vous à venir peut être reprogrammé.']);
        }
        $startsAt = $this->parseStartsAt(trim((string) ($input['starts_at'] ?? '')));
        $staffRaw = $input['staff_id'] ?? $appointment->staffId();
        $staffId = is_numeric($staffRaw) && (int) $staffRaw > 0 ? (int) $staffRaw : null;
        if ($staffId !== null && $this->team->findById($staffId, $tenantId) === null) {
            throw new ValidationException(['staff_id' => 'Personnel introuvable.']);
        }
        $this->assertWithinAvailability($tenantId, $staffId, $startsAt, $appointment->durationMinutes());
        if ($this->appointments->hasOverlap($tenantId, $staffId, $startsAt, $appointment->durationMinutes(), $id)) {
            throw new ValidationException(['starts_at' => 'Ce créneau est déjà réservé.']);
        }
        $this->appointments->updateSchedule($id, $tenantId, $startsAt, $staffId);
        $updated = $this->findAppointment($actor, $id);
        if ($appointment->status() === AppointmentStatus::CONFIRMED) {
            $this->scheduleReminder($tenantId, $updated);
        }
        if ($startsAt !== $appointment->startsAt()) {
            $this->tellClient($updated, 'Votre rendez-vous ' . $updated->serviceName() . ' est déplacé au ' . $this->human($startsAt) . '. Prévenez-nous si cet horaire ne vous convient pas.');
        }
    }

    public function initiateDeposit(User $actor, int $appointmentId): string
    {
        if ($this->payments === null) {
            throw new HttpException(503, 'Paiements non disponibles.');
        }
        $tenantId = $this->tenantId($actor);
        $this->assertReservation($actor, Permission::RESERVATION_APPOINTMENTS);
        $appointment = $this->findAppointment($actor, $appointmentId);
        if ($appointment->depositAmountMinor() < 100) {
            throw new ValidationException(['deposit' => 'Aucun acompte requis pour ce rendez-vous.']);
        }
        if ($appointment->paymentId() !== null) {
            throw new ValidationException(['deposit' => 'Un paiement est déjà lié.']);
        }
        $result = $this->payments->initiate($actor, [
            'amount_minor' => $appointment->depositAmountMinor(),
            'description' => 'Acompte RDV #' . $appointmentId . ' — ' . $appointment->serviceName(),
            'client_id' => $appointment->clientId(),
        ]);
        $this->appointments->attachPayment($appointmentId, $tenantId, $result['payment']->id());

        return $result['checkout_url'];
    }

    /**
     * Créneaux libres pour l'écran « Nouveau rendez-vous ».
     *
     * @return list<array{time: string, starts_at: string, staff_id: ?int}>
     */
    public function slotsFor(User $actor, int $serviceId, string $date, ?int $staffId): array
    {
        $tenantId = $this->tenantId($actor);
        $this->assertReservation($actor, Permission::RESERVATION_VIEW);
        $service = $this->prestations->findById($serviceId, $tenantId);
        if ($service === null) {
            return [];
        }

        return $this->availability->slots($tenantId, $service, $date, $staffId, null, true);
    }

    /**
     * @return list<ReservationAppointment>
     */
    public function appointmentsForClient(User $actor, int $clientId): array
    {
        $tenant = $this->context->tenant();
        if (!$tenant instanceof Tenant || $tenant->sector() !== Sector::RESERVATION || !$actor->can(Permission::RESERVATION_VIEW)) {
            return [];
        }

        return $this->appointments->listForClient($tenant->id(), $clientId, 20);
    }

    public function dashboardCounts(int $tenantId): array
    {
        $dayStart = gmdate('Y-m-d') . ' 00:00:00';
        $dayEnd = gmdate('Y-m-d', strtotime('+1 day')) . ' 00:00:00';

        return [
            'appointments_today' => $this->appointments->countToday($tenantId, $dayStart, $dayEnd),
            'appointments_upcoming' => $this->appointments->countUpcoming($tenantId, $dayEnd),
            'clients_today' => $this->appointments->countClientsToday($tenantId, $dayStart, $dayEnd),
        ];
    }

    /**
     * @return array{prestations: list<ReservationPrestation>, team: list<ReservationTeamMember>, clients: list<\Sesam\Models\Client>}
     */
    public function formCatalog(User $actor, int $selectedClientId = 0): array
    {
        $tenantId = $this->tenantId($actor);
        $this->assertReservation($actor, Permission::RESERVATION_APPOINTMENTS);
        // Les autres clientes sont proposées pendant la saisie du nom (clientSuggestions).
        $selected = $selectedClientId > 0 ? $this->clients->findById($selectedClientId, $tenantId) : null;

        return [
            'prestations' => $this->prestations->listActive($tenantId),
            'team' => $this->team->listActive($tenantId),
            'clients' => $selected !== null ? [$selected] : [],
        ];
    }

    /**
     * Clientes déjà enregistrées qui correspondent au texte tapé (nom ou numéro), pour le formulaire de rendez-vous.
     *
     * @return list<array{id: int, name: string, phone: string, email: string}>
     */
    public function clientSuggestions(User $actor, string $query): array
    {
        $tenantId = $this->tenantId($actor);
        $this->assertReservation($actor, Permission::RESERVATION_APPOINTMENTS);
        $query = mb_substr(trim($query), 0, 60);
        if (mb_strlen($query) < 1) {
            return [];
        }

        return array_map(static fn (\Sesam\Models\Client $client): array => [
            'id' => $client->id(),
            'name' => $client->fullName(),
            'phone' => $client->phone() ?? '',
            'email' => $client->email() ?? '',
        ], $this->clients->suggest($tenantId, $query, 8));
    }

    // ---------------------------------------------------------------- Page publique

    /**
     * @return array{prestations: list<ReservationPrestation>, team: list<ReservationTeamMember>}
     */
    public function publicCatalog(): array
    {
        $tenantId = $this->requirePublicSector();

        return [
            'prestations' => $this->prestations->listActive($tenantId),
            'team' => $this->team->listActive($tenantId),
        ];
    }

    /**
     * @return list<array{time: string, starts_at: string, staff_id: ?int}>
     */
    public function publicSlots(int $serviceId, string $date, ?int $staffId): array
    {
        $tenantId = $this->requirePublicSector();
        $service = $this->prestations->findById($serviceId, $tenantId);
        if ($service === null || !$service->isActive() || !$this->settings->find($tenantId)->isOpen()) {
            return [];
        }

        return $this->availability->slots($tenantId, $service, $date, $staffId);
    }

    /**
     * @param array<string, mixed> $input
     */
    public function publicCreate(array $input): int
    {
        $tenantId = $this->requirePublicSector();
        $settings = $this->settings->find($tenantId);
        if (!$settings->isOpen()) {
            throw new ValidationException(['starts_at' => 'Le salon ne prend pas de réservation en ligne pour le moment.']);
        }
        $clientId = (int) ($input['client_id'] ?? 0);
        $serviceId = (int) ($input['service_id'] ?? 0);
        $staffRaw = $input['staff_id'] ?? null;
        $staffId = is_numeric($staffRaw) && (int) $staffRaw > 0 ? (int) $staffRaw : null;
        $notes = trim((string) ($input['notes'] ?? ''));

        if ($this->clients->findById($clientId, $tenantId) === null) {
            throw new ValidationException(['full_name' => 'Vos informations sont incomplètes.']);
        }
        $service = $this->prestations->findById($serviceId, $tenantId);
        if ($service === null || !$service->isActive()) {
            throw new ValidationException(['service_id' => 'Choisissez une prestation.']);
        }
        if ($staffId !== null && $this->team->findById($staffId, $tenantId) === null) {
            throw new ValidationException(['staff_id' => 'Personnel introuvable.']);
        }
        $startsAt = $this->parseStartsAt(trim((string) ($input['starts_at'] ?? '')));
        if ($staffId !== null && $this->appointments->hasOverlap($tenantId, $staffId, $startsAt, $service->durationMinutes(), null)) {
            throw new ValidationException(['starts_at' => 'Ce créneau est déjà réservé. Choisissez un autre horaire.']);
        }
        $assigned = $this->availability->bookable($tenantId, $service, $startsAt, $staffId);
        if ($assigned === false) {
            throw new ValidationException(['starts_at' => 'Ce créneau est déjà réservé ou n\'est plus disponible. Choisissez un autre horaire.']);
        }

        $appointmentId = $this->appointments->insert($tenantId, [
            'client_id' => $clientId,
            'service_id' => $serviceId,
            'staff_id' => $assigned,
            'starts_at' => $startsAt,
            'duration_minutes' => $service->durationMinutes(),
            'price_minor' => $service->priceMinor(),
            'status' => AppointmentStatus::PENDING,
            'source' => 'online',
            'deposit_amount_minor' => $service->depositMinor(),
            'notes' => $notes !== '' ? mb_substr($notes, 0, 500) : null,
        ]);
        $client = $this->clients->findById($clientId, $tenantId);
        \Sesam\Services\Notifier::tenant(
            $tenantId,
            'reservation.new',
            'Nouvelle réservation en ligne',
            ($client?->fullName() ?? 'Une cliente') . ' · ' . $service->name() . ' · ' . $this->human($startsAt) . ($settings->autoConfirm() ? '' : ' — à confirmer'),
            '/reservation/rendez-vous/' . $appointmentId,
        );

        return $appointmentId;
    }

    /**
     * Sans acompte : confirmé tout de suite si le salon l'a choisi, sinon le salon confirme lui-même.
     */
    public function publicAutoConfirm(int $id): bool
    {
        $tenantId = $this->requirePublicSector();
        if (!$this->settings->find($tenantId)->autoConfirm()) {
            return false;
        }
        $this->publicConfirm($id);

        return true;
    }

    public function publicFind(int $id): ReservationAppointment
    {
        $appointment = $this->appointments->findById($id, $this->requirePublicSector());
        if ($appointment === null) {
            throw new HttpException(404, 'Rendez-vous introuvable.');
        }

        return $appointment;
    }

    public function publicConfirm(int $id): void
    {
        $tenantId = $this->requirePublicSector();
        $appointment = $this->appointments->findById($id, $tenantId);
        if ($appointment === null || $appointment->status() === AppointmentStatus::CANCELLED) {
            return;
        }
        $this->appointments->updateStatus($id, $tenantId, AppointmentStatus::CONFIRMED);
        $this->scheduleReminder($tenantId, $appointment);
    }

    public function publicFindByPayment(int $paymentId): ?ReservationAppointment
    {
        return $this->appointments->findByPaymentId($paymentId, $this->requirePublicSector());
    }

    public function publicAttachPayment(int $appointmentId, int $paymentId): void
    {
        $this->appointments->attachPayment($appointmentId, $this->requirePublicSector(), $paymentId);
    }

    // ---------------------------------------------------------------- Outils internes

    public static function reminderRef(int $appointmentId): string
    {
        return 'rdv-' . $appointmentId;
    }

    /**
     * Rappel automatique à la cliente : la veille (24 h avant) ; si le rendez-vous est pris moins de 24 h
     * avant, 3 h avant. Un seul rappel par rendez-vous : le précédent est remplacé (heure changée).
     * Automatique : il ne dépend pas des droits de la personne qui confirme.
     */
    private function scheduleReminder(int $tenantId, ReservationAppointment $appointment): void
    {
        if ($this->messaging === null) {
            return;
        }
        $timestamp = strtotime($appointment->startsAt() . ' UTC');
        if ($timestamp === false) {
            return;
        }
        $sendAt = $timestamp - 86400;
        if ($sendAt <= time()) {
            $sendAt = $timestamp - 3 * 3600;
        }
        if ($sendAt <= time()) {
            $this->messaging->cancelReminders($tenantId, self::reminderRef($appointment->id()));

            return;
        }
        $client = $this->clients->findById($appointment->clientId(), $tenantId);
        $phone = $client?->phone();
        $email = $client?->email();
        $channel = $phone !== null && $phone !== '' ? MessageChannel::WHATSAPP : MessageChannel::EMAIL;
        try {
            $this->messaging->scheduleAutomatic($tenantId, $channel, (string) ($channel === MessageChannel::WHATSAPP ? $phone : $email), 'rdv_rappel', [
                'name' => $client?->fullName() ?? 'Client',
                'amount' => (string) $appointment->depositAmountMinor(),
                'client' => $client?->fullName() ?? '',
                'details' => $appointment->serviceName() . ' le ' . $this->human($appointment->startsAt()),
                // Secours : si WhatsApp ne remet pas le rappel, il part par e-mail.
                'email' => $channel === MessageChannel::WHATSAPP ? (string) ($email ?? '') : '',
            ], gmdate('Y-m-d H:i:s', $sendAt), self::reminderRef($appointment->id()));
        } catch (\Throwable $exception) {
            $this->logger?->info('Rappel RDV non planifié : ' . $exception->getMessage());
        }
    }

    /** Message WhatsApp à la cliente quand le salon confirme, annule ou déplace son rendez-vous. */
    private function tellClient(ReservationAppointment $appointment, string $text): void
    {
        if ($this->messaging === null) {
            return;
        }
        $salon = $this->context->tenant()?->displayName() ?? 'Votre salon';
        $email = $this->context->id() !== null ? $this->clients->findById($appointment->clientId(), (int) $this->context->id())?->email() : null;
        if (trim($appointment->clientPhone()) === '' && ($email === null || $email === '')) {
            return;
        }
        try {
            $this->messaging->notifyClient($appointment->clientPhone(), $email, $appointment->clientName(), 'Votre rendez-vous chez ' . $salon, $salon . ' — ' . $text);
        } catch (\Throwable $exception) {
            $this->logger?->info('Message RDV non envoyé : ' . $exception->getMessage());
        }
    }

    /**
     * Saisie au salon : la personne doit travailler à cette heure (ses horaires, sinon ceux du salon),
     * ne pas être absente, et le salon ne doit pas être fermé ce jour-là.
     */
    private function assertWithinAvailability(int $tenantId, ?int $staffId, string $startsAt, int $durationMinutes): void
    {
        $timestamp = strtotime($startsAt . ' UTC');
        if ($timestamp === false) {
            throw new ValidationException(['starts_at' => 'Date invalide.']);
        }
        $date = gmdate('Y-m-d', $timestamp);
        $closed = $this->settings->closedOn($tenantId, $date);
        if (isset($closed[0])) {
            throw new ValidationException(['starts_at' => 'Le salon est fermé ce jour-là.']);
        }
        if ($staffId === null) {
            return;
        }
        if (isset($closed[$staffId])) {
            throw new ValidationException(['staff_id' => 'Cette personne est absente ce jour-là.']);
        }
        $weekday = (int) gmdate('N', $timestamp);
        $time = gmdate('H:i:s', $timestamp);
        $endTime = gmdate('H:i:s', $timestamp + ($durationMinutes * 60));
        $slots = $this->team->availability($tenantId, $staffId);
        if ($slots === []) {
            return;
        }
        foreach ($slots as $slot) {
            if ($slot['weekday'] === $weekday && $time >= $slot['starts_at'] && $endTime <= $slot['ends_at']) {
                return;
            }
        }
        throw new ValidationException(['starts_at' => 'Hors des horaires de cette personne.']);
    }

    private function parseStartsAt(string $value): string
    {
        if ($value === '') {
            throw new ValidationException(['starts_at' => 'Choisissez une date et une heure.']);
        }
        $timestamp = strtotime(str_replace('T', ' ', $value) . ' UTC');
        if ($timestamp === false || $timestamp < time() - 60) {
            throw new ValidationException(['starts_at' => 'Date/heure invalide ou passée.']);
        }

        return gmdate('Y-m-d H:i:s', $timestamp);
    }

    /**
     * @param array<string, mixed> $input
     * @return array{name: string, description: ?string, category: ?string, duration_minutes: int, price_minor: int, deposit_minor: int, is_active: int}
     */
    private function validatedPrestation(array $input): array
    {
        $errors = [];
        $name = trim((string) ($input['name'] ?? ''));
        $description = trim((string) ($input['description'] ?? ''));
        $category = trim((string) ($input['category'] ?? ''));
        $duration = (int) ($input['duration_minutes'] ?? 0);
        $price = (int) preg_replace('/\D+/', '', (string) ($input['price_minor'] ?? '0'));
        $deposit = (int) preg_replace('/\D+/', '', (string) ($input['deposit_minor'] ?? '0'));
        if ($name === '' || mb_strlen($name) > 120) {
            $errors['name'] = 'Indiquez le nom de la prestation (120 caractères max.).';
        }
        if (mb_strlen($description) > 500) {
            $errors['description'] = 'Description trop longue (500 caractères max.).';
        }
        if (mb_strlen($category) > 60) {
            $errors['category'] = 'Catégorie trop longue.';
        }
        if ($duration < 5 || $duration > 480) {
            $errors['duration_minutes'] = 'Durée entre 5 et 480 minutes.';
        }
        if ($price < 0 || $deposit < 0 || $deposit > $price) {
            $errors['price_minor'] = 'Tarif ou acompte invalide : l\'acompte ne peut pas dépasser le prix.';
        }
        if ($errors !== []) {
            throw new ValidationException($errors);
        }

        return [
            'name' => $name,
            'description' => $description !== '' ? $description : null,
            'category' => $category !== '' ? mb_convert_case(mb_substr($category, 0, 1), MB_CASE_UPPER) . mb_substr($category, 1) : null,
            'duration_minutes' => $duration,
            'price_minor' => $price,
            'deposit_minor' => $deposit,
            'is_active' => array_key_exists('is_active', $input) ? (!empty($input['is_active']) ? 1 : 0) : 1,
        ];
    }

    /**
     * @param array<string, mixed> $input
     * @return array{name: string, role: ?string, phone: ?string}
     */
    private function validatedMember(array $input): array
    {
        $name = trim((string) ($input['name'] ?? ''));
        $role = trim((string) ($input['role_label'] ?? ''));
        $phone = trim((string) ($input['phone'] ?? ''));
        if ($name === '' || mb_strlen($name) > 120) {
            throw new ValidationException(['name' => 'Nom invalide.']);
        }
        if (mb_strlen($role) > 80 || mb_strlen($phone) > 40) {
            throw new ValidationException(['role_label' => 'Rôle ou téléphone trop long.']);
        }

        return ['name' => $name, 'role' => $role !== '' ? $role : null, 'phone' => $phone !== '' ? $phone : null];
    }

    /**
     * @return list<int>
     */
    private function validServiceIds(int $tenantId, mixed $raw): array
    {
        if (!is_array($raw)) {
            return [];
        }
        $ids = [];
        foreach ($raw as $id) {
            $id = (int) $id;
            if ($id > 0 && $this->prestations->findById($id, $tenantId) !== null) {
                $ids[] = $id;
            }
        }

        return $ids;
    }

    /**
     * @param array<string, mixed> $input
     * @return array{0: string, 1: string, 2: ?string}
     */
    private function validatedPeriod(array $input): array
    {
        $from = trim((string) ($input['starts_on'] ?? ''));
        $to = trim((string) ($input['ends_on'] ?? '')) ?: $from;
        $reason = trim((string) ($input['reason'] ?? ''));
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $from) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $to) || $to < $from) {
            throw new ValidationException(['starts_on' => 'Indiquez une date de début et une date de fin valides.']);
        }
        if (mb_strlen($reason) > 160) {
            throw new ValidationException(['reason' => 'Motif trop long.']);
        }

        return [$from, $to, $reason !== '' ? $reason : null];
    }

    private function findOrCreateClient(int $tenantId, string $name, string $phone, string $activity): int
    {
        if ($phone !== '') {
            $existing = $this->clients->findByPhone($phone, $tenantId) ?? $this->clients->findByPhoneDigits($phone, $tenantId);
            if ($existing !== null) {
                return $existing->id();
            }
        }
        $id = $this->clients->insert($tenantId, [
            'full_name' => $name,
            'email' => null,
            'phone' => $phone !== '' ? $phone : null,
            'status' => ClientStatus::ACTIVE,
        ]);
        $this->activities->insert($tenantId, $id, null, 'reservation', $activity);

        return $id;
    }

    private function clientWhatsApp(Tenant $tenant, ReservationAppointment $appointment): string
    {
        $phone = WhatsApp::normalize($appointment->clientPhone());
        if (!WhatsApp::isValid($phone)) {
            return '';
        }
        $when = $this->human($appointment->startsAt());
        $message = match ($appointment->status()) {
            AppointmentStatus::CONFIRMED => 'Bonjour ' . $appointment->clientName() . ', votre rendez-vous ' . $appointment->serviceName() . ' est confirmé pour ' . $when . '. À bientôt !',
            AppointmentStatus::CANCELLED => 'Bonjour ' . $appointment->clientName() . ', votre rendez-vous du ' . $when . ' a été annulé. Voulez-vous choisir un autre créneau ?',
            AppointmentStatus::COMPLETED => 'Merci ' . $appointment->clientName() . ' pour votre visite ! À très bientôt.',
            AppointmentStatus::NO_SHOW => 'Bonjour ' . $appointment->clientName() . ', nous ne vous avons pas vu(e) ' . $when . '. Souhaitez-vous reprendre rendez-vous ?',
            default => 'Bonjour ' . $appointment->clientName() . ', nous avons bien reçu votre demande de rendez-vous ' . $appointment->serviceName() . ' pour ' . $when . '.',
        };

        return WhatsApp::link($phone, $message . ' — ' . $tenant->displayName());
    }

    public function human(string $dateTime): string
    {
        $timestamp = strtotime($dateTime . ' UTC');
        if ($timestamp === false) {
            return $dateTime;
        }
        $days = ['', 'lundi', 'mardi', 'mercredi', 'jeudi', 'vendredi', 'samedi', 'dimanche'];

        return $days[(int) gmdate('N', $timestamp)] . ' ' . gmdate('d/m', $timestamp) . ' à ' . gmdate('H:i', $timestamp);
    }

    public static function money(int $amount): string
    {
        return Money::format($amount);
    }

    /**
     * Heures affichées dans l'agenda : celles du salon, élargies si un rendez-vous déborde.
     *
     * @param array<int, list<array{0: string, 1: string}>> $hours
     * @param array<string, list<ReservationAppointment>> $byDay
     * @return array{0: int, 1: int}
     */
    private function agendaBounds(array $hours, array $byDay): array
    {
        $open = 8;
        $close = 20;
        if ($hours !== []) {
            $open = 23;
            $close = 1;
            foreach ($hours as $ranges) {
                foreach ($ranges as [$from, $to]) {
                    $open = min($open, (int) substr($from, 0, 2));
                    $close = max($close, (int) ceil(((int) substr($to, 0, 2) * 60 + (int) substr($to, 3, 2)) / 60));
                }
            }
        }
        foreach ($byDay as $items) {
            foreach ($items as $item) {
                $start = (int) substr($item->startsAt(), 11, 2);
                $end = (int) ceil(((int) substr($item->endsAt(), 11, 2) * 60 + (int) substr($item->endsAt(), 14, 2)) / 60);
                $open = min($open, $start);
                $close = max($close, min(24, $end));
            }
        }

        return [$open, max($open + 1, $close)];
    }

    private function requirePublicSector(): int
    {
        $tenant = $this->context->tenant();
        if (!$tenant instanceof Tenant || $tenant->sector() !== Sector::RESERVATION) {
            throw new HttpException(404, 'Page introuvable.');
        }

        return $tenant->id();
    }

    private function tenantId(User $actor): int
    {
        if ($actor->tenantId() === null) {
            throw new HttpException(403, 'Vous n\'avez pas l\'autorisation d\'accéder à cette page.');
        }
        $tenantId = $this->context->requireId();
        if ($tenantId !== $actor->tenantId()) {
            throw new HttpException(403, 'Vous n\'avez pas l\'autorisation d\'accéder à cette page.');
        }

        return $tenantId;
    }

    private function assertReservation(User $actor, string $permission): void
    {
        if (!$actor->can($permission)) {
            throw new HttpException(403, 'Vous n\'avez pas l\'autorisation d\'accéder à cette page.');
        }
        $tenant = $this->context->tenant();
        if (!$tenant instanceof Tenant || $tenant->sector() !== Sector::RESERVATION) {
            throw new HttpException(403, 'Ce module est réservé aux commerces secteur Réservation.');
        }
    }
}
