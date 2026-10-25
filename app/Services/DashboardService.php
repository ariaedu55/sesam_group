<?php

declare(strict_types=1);

namespace Sesam\Services;

use Sesam\Core\Crm\ClientStatus;
use Sesam\Core\Dashboard\SectorDashboard;
use Sesam\Core\Database\Connection;
use Sesam\Core\Loyer\LeaseStatus;
use Sesam\Core\Loyer\RentDueStatus;
use Sesam\Core\Payment\PaymentStatus;
use Sesam\Core\Reservation\AppointmentStatus;
use Sesam\Core\Tenant\Sector;
use Sesam\Models\Tenant;
use Sesam\Models\User;
use Sesam\Repositories\ClientRepository;
use Sesam\Repositories\LoyerLeaseRepository;
use Sesam\Repositories\LoyerRentDueRepository;
use Sesam\Repositories\PaymentRepository;
use Sesam\Repositories\ReservationAppointmentRepository;
use Sesam\Repositories\ReservationTeamRepository;

final class DashboardService extends Service
{
    private const PREVIEW = 5;

    private readonly PaymentRepository $payments;
    private readonly ReservationAppointmentRepository $appointments;
    private readonly LoyerRentDueRepository $loyerDues;
    private readonly LoyerLeaseRepository $loyerLeases;
    private readonly ClientRepository $clients;
    private readonly ReservationTeamRepository $reservationTeam;

    /**
     * @param array{host: string, port: string, name: string, user: string, password: string} $database
     */
    public function __construct(array $database)
    {
        $pdo = Connection::make($database);
        $this->payments = new PaymentRepository($pdo);
        $this->appointments = new ReservationAppointmentRepository($pdo);
        $this->loyerDues = new LoyerRentDueRepository($pdo);
        $this->loyerLeases = new LoyerLeaseRepository($pdo);
        $this->clients = new ClientRepository($pdo);
        $this->reservationTeam = new ReservationTeamRepository($pdo);
    }

    /**
     * @return array{
     *     config: array{
     *         activityTitle: string,
     *         kpis: list<array{key: string, label: string}>,
     *         quickActions: list<array{label: string, hint: string}>,
     *         activityEmpty: string,
     *         upcomingTitle: string,
     *         workTitle: string,
     *         queueTitle: string,
     *         peopleTitle: string
     *     },
     *     counts: array<string, int>,
     *     calendar: array{title: string, weekdays: list<string>, cells: list<array{label: string, today: bool}>},
     *     upcoming: list<array{title: string, meta: string, when: string, href: string}>,
     *     work: list<array{name: string, date: string, condition: string, status: string, href: string}>,
     *     queue: list<array{label: string, qty: string, extra: string, when: string, status: string, href: string}>,
     *     people: list<array{name: string, role: string, status: string, href: string}>
     * }
     */
    public function forTenant(Tenant $tenant): array
    {
        $config = SectorDashboard::config($tenant->sector());
        $counts = [];
        $tenantId = $tenant->id();
        $todayStart = gmdate('Y-m-d') . ' 00:00:00';
        $todayEnd = gmdate('Y-m-d', strtotime('+1 day')) . ' 00:00:00';
        $tomorrow = gmdate('Y-m-d', strtotime('+1 day')) . ' 00:00:00';
        $reservationKpis = $tenant->sector() === Sector::RESERVATION;
        $loyerKpis = $tenant->sector() === Sector::LOYER;
        $todayDate = gmdate('Y-m-d');
        foreach ($config['kpis'] as $kpi) {
            $key = (string) ($kpi['key'] ?? '');
            $counts[$key] = match ($key) {
                'payments_pending' => $this->payments->countByStatus($tenantId, PaymentStatus::PENDING),
                'payments_received' => $this->payments->countPaidSince($tenantId, $todayStart),
                'appointments_today' => $reservationKpis ? $this->appointments->countToday($tenantId, $todayStart, $todayEnd) : 0,
                'appointments_upcoming' => $reservationKpis ? $this->appointments->countUpcoming($tenantId, $tomorrow) : 0,
                'clients_today' => $reservationKpis ? $this->appointments->countClientsToday($tenantId, $todayStart, $todayEnd) : 0,
                'rent_due_today' => $loyerKpis ? $this->loyerDues->countDueToday($tenantId, $todayDate) : 0,
                'rent_late' => $loyerKpis ? $this->loyerDues->countLate($tenantId, $todayDate) : 0,
                'leases_active' => $loyerKpis ? $this->loyerLeases->countActive($tenantId) : 0,
                default => 0,
            };
        }

        return [
            'config' => $config,
            'counts' => $counts,
            'calendar' => $this->calendarMonth(),
            'upcoming' => $this->upcomingRows($tenant),
            'work' => $this->workRows($tenant),
            'queue' => $this->queueRows($tenantId, $tenant),
            'people' => $this->peopleRows($tenant),
        ];
    }

    public function greetingName(User $user): string
    {
        $name = trim($user->fullName());

        return $name !== '' ? $name : 'Bonjour';
    }

    /**
     * @return array{title: string, weekdays: list<string>, cells: list<array{label: string, today: bool}>}
     */
    private function calendarMonth(): array
    {
        $now = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        $start = $now->modify('first day of this month');
        $months = [
            1 => 'Janvier', 2 => 'Février', 3 => 'Mars', 4 => 'Avril',
            5 => 'Mai', 6 => 'Juin', 7 => 'Juillet', 8 => 'Août',
            9 => 'Septembre', 10 => 'Octobre', 11 => 'Novembre', 12 => 'Décembre',
        ];
        $monthIndex = (int) $start->format('n');
        $daysInMonth = (int) $start->format('t');
        $pad = (int) $start->format('N') - 1;
        $today = $now->format('Y-m-d');
        $cells = [];
        for ($i = 0; $i < $pad; ++$i) {
            $cells[] = ['label' => '', 'today' => false];
        }
        for ($day = 1; $day <= $daysInMonth; ++$day) {
            $date = $start->format('Y-m-') . str_pad((string) $day, 2, '0', STR_PAD_LEFT);
            $cells[] = ['label' => (string) $day, 'today' => $date === $today];
        }

        return [
            'title' => ($months[$monthIndex] ?? '') . ' ' . $start->format('Y'),
            'weekdays' => ['L', 'M', 'M', 'J', 'V', 'S', 'D'],
            'cells' => $cells,
        ];
    }

    /**
     * @return list<array{title: string, meta: string, when: string, href: string}>
     */
    private function upcomingRows(Tenant $tenant): array
    {
        $tenantId = $tenant->id();
        $rows = [];
        if ($tenant->sector() === Sector::RESERVATION) {
            foreach ($this->appointments->listForTenant($tenantId, self::PREVIEW, 0) as $item) {
                $rows[] = [
                    'title' => $item->clientName(),
                    'meta' => $item->serviceName(),
                    'when' => $this->formatDateTime($item->startsAt()),
                    'href' => '/reservation/rendez-vous/' . $item->id(),
                ];
            }
        } elseif ($tenant->sector() === Sector::LOYER) {
            foreach ($this->loyerDues->listForTenant($tenantId, self::PREVIEW, 0, null, gmdate('Y-m-d')) as $item) {
                $rows[] = [
                    'title' => $item->clientName(),
                    'meta' => $item->propertyName() . ' ' . $item->unitLabel(),
                    'when' => $this->formatDate($item->dueDate()),
                    'href' => '/immo/loyers/' . $item->id(),
                ];
            }
        }

        return $rows;
    }

    /**
     * @return list<array{name: string, date: string, condition: string, status: string, href: string}>
     */
    private function workRows(Tenant $tenant): array
    {
        $tenantId = $tenant->id();
        $rows = [];
        if ($tenant->sector() === Sector::RESERVATION) {
            foreach ($this->appointments->listForTenant($tenantId, self::PREVIEW, 0) as $item) {
                $rows[] = [
                    'name' => $item->clientName(),
                    'date' => $this->formatDateTime($item->startsAt()),
                    'condition' => $item->serviceName(),
                    'status' => AppointmentStatus::label($item->status()),
                    'href' => '/reservation/rendez-vous/' . $item->id(),
                ];
            }
        } elseif ($tenant->sector() === Sector::LOYER) {
            foreach ($this->loyerLeases->listForTenant($tenantId, self::PREVIEW, 0) as $item) {
                $rows[] = [
                    'name' => $item->clientName(),
                    'date' => $this->formatDate($item->startsOn()),
                    'condition' => $item->propertyName() . ' ' . $item->unitLabel(),
                    'status' => LeaseStatus::label($item->status()),
                    'href' => '/immo/baux/' . $item->id(),
                ];
            }
        }

        return $rows;
    }

    /**
     * @return list<array{label: string, qty: string, extra: string, when: string, status: string, href: string}>
     */
    private function queueRows(int $tenantId, Tenant $tenant): array
    {
        $rows = [];
        foreach ($this->payments->listForTenant($tenantId, self::PREVIEW, 0) as $item) {
            $name = $item->clientName() !== '' ? $item->clientName() : $item->reference();
            $rows[] = [
                'label' => $name,
                'qty' => number_format($item->amountMinor(), 0, ',', ' ') . ' FCFA',
                'extra' => $item->description() !== '' ? $item->description() : $item->reference(),
                'when' => $this->formatDate($item->createdAt()),
                'status' => PaymentStatus::label($item->status()),
                'href' => '/paiements/' . $item->id(),
            ];
        }

        return $rows;
    }

    /**
     * @return list<array{name: string, role: string, status: string, href: string}>
     */
    private function peopleRows(Tenant $tenant): array
    {
        $tenantId = $tenant->id();
        $rows = [];
        if ($tenant->sector() === Sector::RESERVATION) {
            foreach ($this->reservationTeam->listAll($tenantId) as $member) {
                if (count($rows) >= self::PREVIEW) {
                    break;
                }
                $rows[] = [
                    'name' => $member->name(),
                    'role' => 'Équipe',
                    'status' => $member->isActive() ? 'Disponible' : 'Inactif',
                    'href' => '/reservation/equipe',
                ];
            }

            return $rows;
        }

        foreach ($this->clients->search($tenantId, '', ClientStatus::ACTIVE, null, self::PREVIEW, 0) as $client) {
            $rows[] = [
                'name' => $client->fullName(),
                'role' => $client->phone() ?? $client->email() ?? 'Client',
                'status' => 'Actif',
                'href' => '/clients/' . $client->id(),
            ];
        }

        return $rows;
    }

    private function formatDateTime(string $value): string
    {
        $timestamp = strtotime($value);
        if ($timestamp === false) {
            return $value;
        }

        return gmdate('d/m/Y H:i', $timestamp);
    }

    private function formatDate(string $value): string
    {
        $timestamp = strtotime($value);
        if ($timestamp === false) {
            return $value;
        }

        return gmdate('d/m/Y', $timestamp);
    }
}
