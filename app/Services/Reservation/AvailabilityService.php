<?php

declare(strict_types=1);

namespace Sesam\Services\Reservation;

use Sesam\Models\ReservationPrestation;
use Sesam\Models\ReservationSettings;
use Sesam\Models\ReservationTeamMember;
use Sesam\Repositories\ReservationAppointmentRepository;
use Sesam\Repositories\ReservationSettingsRepository;
use Sesam\Repositories\ReservationTeamRepository;

/**
 * Calcule les créneaux libres d'une journée pour une prestation.
 *
 * Règles, dans l'ordre :
 * - le salon est fermé ce jour (fermeture) ou hors de ses horaires d'ouverture → aucun créneau ;
 *   tant que le salon n'a pas réglé ses horaires, seules les disponibilités de l'équipe comptent ;
 * - une personne doit savoir faire la prestation, ne pas être absente, travailler à cette heure ;
 *   sans horaires personnels, elle suit les horaires du salon ;
 * - le créneau, plus le temps de battement, ne chevauche aucun rendez-vous actif de la personne ;
 * - délai minimum avant le rendez-vous et réservation au plus tard N jours à l'avance.
 * Sans équipe enregistrée, le salon compte comme une seule personne.
 */
final class AvailabilityService
{
    private const DEFAULT_OPEN = '09:00:00';
    private const DEFAULT_CLOSE = '19:00:00';

    public function __construct(
        private readonly ReservationSettingsRepository $settings,
        private readonly ReservationTeamRepository $team,
        private readonly ReservationAppointmentRepository $appointments,
    ) {
    }

    /**
     * @return list<array{time: string, starts_at: string, staff_id: ?int}>
     */
    public function slots(int $tenantId, ReservationPrestation $service, string $date, ?int $staffId, ?int $now = null, bool $ignoreNotice = false): array
    {
        $now ??= time();
        $settings = $this->settings->find($tenantId);
        $day = strtotime($date . ' 00:00:00 UTC');
        if ($day === false || gmdate('Y-m-d', $day) !== $date) {
            return [];
        }
        $today = strtotime(gmdate('Y-m-d', $now) . ' 00:00:00 UTC');
        if ($day < $today || $day > $today + $settings->maxAdvanceDays() * 86400) {
            return [];
        }
        $earliest = $ignoreNotice ? $now : $now + $settings->minNoticeHours() * 3600;
        $step = $settings->slotStepMinutes() * 60;
        $length = $service->durationMinutes() * 60;
        $buffer = $settings->bufferMinutes() * 60;

        $slots = [];
        foreach ($this->resources($tenantId, $service, $date, $staffId, $settings) as [$resourceId, $windows, $busy]) {
            foreach ($windows as [$open, $close]) {
                for ($start = $open; $start + $length <= $close; $start += $step) {
                    if ($start < $earliest || isset($slots[$start])) {
                        continue;
                    }
                    if ($this->isFree($start, $start + $length, $busy, $buffer)) {
                        $slots[$start] = ['time' => gmdate('H:i', $start), 'starts_at' => gmdate('Y-m-d H:i:s', $start), 'staff_id' => $resourceId];
                    }
                }
            }
        }
        ksort($slots);

        return array_values($slots);
    }

    /**
     * Vérifie un horaire précis (réservation en ligne). Renvoie la personne retenue (null : salon sans équipe),
     * ou false si l'horaire n'est pas réservable.
     */
    public function bookable(int $tenantId, ReservationPrestation $service, string $startsAt, ?int $staffId, ?int $now = null): int|false|null
    {
        $now ??= time();
        $settings = $this->settings->find($tenantId);
        $start = strtotime($startsAt . ' UTC');
        if ($start === false) {
            return false;
        }
        $date = gmdate('Y-m-d', $start);
        $today = strtotime(gmdate('Y-m-d', $now) . ' 00:00:00 UTC');
        if ($start < $now + $settings->minNoticeHours() * 3600 || $start > $today + ($settings->maxAdvanceDays() + 1) * 86400) {
            return false;
        }
        $end = $start + $service->durationMinutes() * 60;
        foreach ($this->resources($tenantId, $service, $date, $staffId, $settings) as [$resourceId, $windows, $busy]) {
            foreach ($windows as [$open, $close]) {
                if ($start >= $open && $end <= $close && $this->isFree($start, $end, $busy, $settings->bufferMinutes() * 60)) {
                    return $resourceId;
                }
            }
        }

        return false;
    }

    /**
     * Personnes (ou le salon seul) avec leurs plages de travail du jour et leurs rendez-vous.
     *
     * @return list<array{0: ?int, 1: list<array{0: int, 1: int}>, 2: list<array{0: int, 1: int}>}>
     */
    private function resources(int $tenantId, ReservationPrestation $service, string $date, ?int $staffId, ReservationSettings $settings): array
    {
        $closed = $this->settings->closedOn($tenantId, $date);
        if (isset($closed[0])) {
            return [];
        }
        $weekday = (int) gmdate('N', (int) strtotime($date . ' 00:00:00 UTC'));
        $salonHours = $settings->hours();
        $salonWindows = $salonHours === []
            ? null
            : $this->toTimestamps($date, $salonHours[$weekday] ?? []);
        if ($salonWindows === []) {
            return [];
        }

        $dayStart = $date . ' 00:00:00';
        $dayEnd = gmdate('Y-m-d', (int) strtotime($date . ' 00:00:00 UTC') + 86400) . ' 00:00:00';
        $busyByStaff = [];
        foreach ($this->appointments->listBetween($tenantId, $dayStart, $dayEnd, true) as $appointment) {
            $start = (int) strtotime($appointment->startsAt() . ' UTC');
            $busyByStaff[$appointment->staffId() ?? 0][] = [$start, $start + $appointment->durationMinutes() * 60];
        }

        $members = array_values(array_filter(
            $this->team->listActive($tenantId),
            static fn (ReservationTeamMember $member): bool => $member->does($service->id())
                && ($staffId === null || $member->id() === $staffId)
        ));
        if ($members === []) {
            if ($staffId !== null) {
                return [];
            }
            $fallback = $salonWindows ?? $this->toTimestamps($date, [[self::DEFAULT_OPEN, self::DEFAULT_CLOSE]]);
            $allBusy = array_merge(...array_values($busyByStaff ?: [[]]));

            return [[null, $fallback, $allBusy]];
        }

        $resources = [];
        foreach ($members as $member) {
            if (isset($closed[$member->id()])) {
                continue;
            }
            $own = $this->team->availability($tenantId, $member->id());
            if ($own === []) {
                $windows = $salonWindows ?? $this->toTimestamps($date, [[self::DEFAULT_OPEN, self::DEFAULT_CLOSE]]);
            } else {
                $ranges = [];
                foreach ($own as $slot) {
                    if ($slot['weekday'] === $weekday) {
                        $ranges[] = [$slot['starts_at'], $slot['ends_at']];
                    }
                }
                $windows = $this->toTimestamps($date, $ranges);
                if ($salonWindows !== null) {
                    $windows = $this->intersect($windows, $salonWindows);
                }
            }
            if ($windows !== []) {
                $resources[] = [$member->id(), $windows, $busyByStaff[$member->id()] ?? []];
            }
        }

        return $resources;
    }

    /**
     * @param list<array{0: int, 1: int}> $busy
     */
    private function isFree(int $start, int $end, array $busy, int $buffer): bool
    {
        foreach ($busy as [$busyStart, $busyEnd]) {
            if ($start < $busyEnd + $buffer && $end + $buffer > $busyStart) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param list<array{0: string, 1: string}> $ranges
     * @return list<array{0: int, 1: int}>
     */
    private function toTimestamps(string $date, array $ranges): array
    {
        $windows = [];
        foreach ($ranges as [$open, $close]) {
            $from = strtotime($date . ' ' . $open . ' UTC');
            $to = strtotime($date . ' ' . $close . ' UTC');
            if ($from !== false && $to !== false && $to > $from) {
                $windows[] = [$from, $to];
            }
        }

        return $windows;
    }

    /**
     * @param list<array{0: int, 1: int}> $a
     * @param list<array{0: int, 1: int}> $b
     * @return list<array{0: int, 1: int}>
     */
    private function intersect(array $a, array $b): array
    {
        $out = [];
        foreach ($a as [$aStart, $aEnd]) {
            foreach ($b as [$bStart, $bEnd]) {
                $start = max($aStart, $bStart);
                $end = min($aEnd, $bEnd);
                if ($end > $start) {
                    $out[] = [$start, $end];
                }
            }
        }

        return $out;
    }
}
