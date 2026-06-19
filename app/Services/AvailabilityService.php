<?php

namespace App\Services;

use App\Models\Court;
use Carbon\Carbon;
use Illuminate\Validation\ValidationException;

/**
 * Authoritative availability logic for the time-first booking flow. Mirrors the
 * rules previously baked into PricingService::getAvailableSlots + the guards in
 * BookingService::create, but evaluates an *arbitrary* start/end range (any
 * minute) and explains why a range is unavailable + suggests nearby openings.
 */
class AvailabilityService
{
    private const BUSY_STATUSES = ['pending', 'confirmed', 'active'];

    public function __construct(private readonly PricingService $pricingService) {}

    /**
     * Evaluate one requested range. Returns a structured verdict the UI renders
     * directly and BookingService re-uses as a server-side guard.
     */
    public function evaluate(Court $court, string $date, string $start, string $end, ?int $exceptId = null): array
    {
        $tz     = $court->tenant?->timezone ?: config('app.timezone');
        $startC = Carbon::parse($date . ' ' . $start, $tz);
        $endC   = Carbon::parse($date . ' ' . $end, $tz);

        $reasons  = [];
        $conflict = null;

        // Range sanity.
        $valid = $endC->gt($startC);
        $duration = $valid ? (int) $startC->diffInMinutes($endC) : 0;
        if (! $valid) {
            $reasons[] = ['code' => 'invalid_range', 'message' => 'End time must be after the start time.'];
        }

        // Whole-court status block.
        if (in_array($court->status, ['maintenance', 'closed'], true)) {
            $reasons[] = ['code' => 'court_' . $court->status, 'message' => 'This court is currently ' . $court->status . '.'];
        }

        // Operating hours for that weekday.
        [$open, $close, $isOpenDay] = $this->operatingWindow($court, $date, $tz);
        if (! $isOpenDay) {
            $reasons[] = ['code' => 'closed_day', 'message' => 'The venue is closed on this day.'];
        } elseif ($valid) {
            if ($startC->format('H:i') < $open || $endC->format('H:i') > $close) {
                $reasons[] = ['code' => 'outside_hours', 'message' => 'Outside operating hours (' . $this->label($open) . ' – ' . $this->label($close) . ').'];
            }
        }

        // Per-court duration bounds.
        if ($valid) {
            $min = (int) ($court->min_booking_minutes ?: 0);
            $max = (int) ($court->max_booking_minutes ?: 0);
            if ($min > 0 && $duration < $min) {
                $reasons[] = ['code' => 'below_min', 'message' => "This court has a minimum of {$min} minutes."];
            }
            if ($max > 0 && $duration > $max) {
                $reasons[] = ['code' => 'above_max', 'message' => "This court has a maximum of {$max} minutes."];
            }
        }

        // Must be strictly in the future (tenant wall-clock).
        if ($valid && $startC->lte(Carbon::now($tz))) {
            $reasons[] = ['code' => 'past', 'message' => 'That start time has already passed. Please pick a future time.'];
        }

        // Booking overlap — mirrors BookingService::checkAvailability exactly.
        if ($valid) {
            $startHis = $startC->format('H:i:s');
            $endHis   = $endC->format('H:i:s');

            $booking = $court->bookings()
                ->where('booking_date', $date)
                ->whereIn('status', self::BUSY_STATUSES)
                ->when($exceptId, fn ($q) => $q->where('id', '!=', $exceptId))
                ->where('start_time', '<', $endHis)
                ->where('end_time', '>', $startHis)
                ->orderBy('start_time')
                ->first(['id', 'start_time', 'end_time', 'status']);

            if ($booking) {
                $bs = substr($this->his($booking->start_time), 0, 5);
                $be = substr($this->his($booking->end_time), 0, 5);
                $reasons[] = ['code' => 'overlap_booking', 'message' => 'Overlaps an existing booking (' . $this->label($bs) . ' – ' . $this->label($be) . ').'];
                $conflict = ['start' => $bs, 'end' => $be, 'label' => $this->label($bs) . ' – ' . $this->label($be), 'kind' => 'booking'];
            }
        }

        $available = empty($reasons);

        $pricing = null;
        if ($available) {
            $p = $this->pricingService->calculate($court, $startC, $endC, $duration);
            $pricing = [
                'rate'        => $p['rate'],
                'total'       => $p['total'],
                'start_label' => $startC->format('g:i A'),
                'end_label'   => $endC->format('g:i A'),
            ];
        }

        return [
            'available'   => $available,
            'duration'    => $duration,
            'reasons'     => $reasons,
            'conflict'    => $conflict,
            'pricing'     => $pricing,
            'suggestions' => $available ? [] : $this->suggestions($court, $date, $duration > 0 ? $duration : 60, $start),
        ];
    }

    /**
     * Server-side guard for the rules that the old slot-prefiltering used to
     * enforce implicitly: whole-court status (maintenance/closed) for all booking
     * types, plus operating hours + duration bounds (scheduled only — walk-ins
     * start "now" and own their cap/bump duration logic). Booking overlap,
     * future-time and the advance horizon stay in BookingService::create.
     * Throws ValidationException so it surfaces like every other create() guard.
     */
    public function assertBookable(Court $court, string $date, string $start, string $end, string $type = 'online'): void
    {
        $tz     = $court->tenant?->timezone ?: config('app.timezone');
        $startC = Carbon::parse($date . ' ' . $start, $tz);
        $endC   = Carbon::parse($date . ' ' . $end, $tz);

        $errors = [];

        if (in_array($court->status, ['maintenance', 'closed'], true)) {
            $errors['court_status'] = 'This court is currently ' . $court->status . ' and cannot be booked.';
        }

        [$open, $close, $isOpenDay] = $this->operatingWindow($court, $date, $tz);
        if (! $isOpenDay) {
            $errors['booking_date'] = 'The venue is closed on this day.';
        } elseif ($this->toMinutes($startC->format('H:i')) < $this->toMinutes($open)
               || $this->toMinutes($startC->format('H:i')) >= $this->toMinutes($close)
               || $this->toMinutes($endC->format('H:i')) > $this->toMinutes($close)
               || $endC->format('H:i:s') < $startC->format('H:i:s')) {
            $errors['time_slot'] = 'Selected time is outside operating hours (' . $this->label($open) . ' – ' . $this->label($close) . ').';
        }

        if ($type !== 'walk_in') {
            $duration = $endC->gt($startC) ? (int) $startC->diffInMinutes($endC) : 0;
            $min = (int) ($court->min_booking_minutes ?: 0);
            $max = (int) ($court->max_booking_minutes ?: 0);
            if ($min > 0 && $duration < $min) {
                $errors['end_time'] = "This court requires a minimum of {$min} minutes.";
            }
            if ($max > 0 && $duration > $max) {
                $errors['end_time'] = "This court allows a maximum of {$max} minutes.";
            }
        }

        if ($errors) {
            throw ValidationException::withMessages($errors);
        }
    }

    /**
     * Nearest free windows of the requested duration, ordered by closeness to the
     * requested start. Re-uses the (now block-aware) slot generator.
     */
    public function suggestions(Court $court, string $date, int $duration, string $around, int $limit = 3): array
    {
        // A maintenance/closed court can't be booked at any time, so the slot
        // generator (which is status-blind) would otherwise offer windows that
        // all fail on click. Surface no suggestions instead.
        if (in_array($court->status, ['maintenance', 'closed'], true)) {
            return [];
        }

        $slots = $this->pricingService->getAvailableSlots($court, $date, $duration, 30);
        if (empty($slots)) {
            return [];
        }

        $target = $this->toMinutes($around);

        usort($slots, function ($a, $b) use ($target) {
            return abs($this->toMinutes($a['start']) - $target) <=> abs($this->toMinutes($b['start']) - $target);
        });

        return array_map(fn ($s) => [
            'start'       => $s['start'],
            'end'         => $s['end'],
            'start_label' => $s['start_label'],
            'end_label'   => $s['end_label'],
            'duration'    => $s['duration'],
            'total'       => $s['total'],
        ], array_slice($slots, 0, $limit));
    }

    /**
     * Day timeline for the visual schedule: operating window + busy segments
     * (bookings + blocks). $detailed exposes customer / block identity for staff;
     * customers see generic "Booked" labels.
     */
    public function timeline(Court $court, string $date, bool $detailed = false): array
    {
        $tz = $court->tenant?->timezone ?: config('app.timezone');
        [$open, $close, $isOpenDay] = $this->operatingWindow($court, $date, $tz);

        $segments = [];

        $bookings = $court->bookings()
            ->where('booking_date', $date)
            ->whereIn('status', self::BUSY_STATUSES)
            ->when($detailed, fn ($q) => $q->with('customer:id,name'))
            ->get(['id', 'customer_id', 'booking_number', 'start_time', 'end_time', 'status']);

        foreach ($bookings as $b) {
            $s = substr($this->his($b->start_time), 0, 5);
            $e = substr($this->his($b->end_time), 0, 5);
            $segments[] = [
                'start'          => $s,
                'end'            => $e,
                'start_label'    => $this->label($s),
                'end_label'      => $this->label($e),
                'kind'           => 'booking',
                'status'         => $b->status,
                'color'          => $b->status === 'pending' ? 'yellow' : 'red',
                'label'          => $detailed ? trim(($b->customer?->name ?? 'Guest')) : 'Booked',
                'booking_number' => $detailed ? $b->booking_number : null,
            ];
        }

        usort($segments, fn ($a, $b) => strcmp($a['start'], $b['start']));

        return [
            'date'         => $date,
            'open'         => $open,
            'close'        => $close,
            'is_closed'    => ! $isOpenDay,
            'court_status' => $court->status,
            'segments'     => array_values($segments),
        ];
    }

    /** Branch operating window for the weekday of $date: [open, close, isOpenDay]. */
    public function operatingWindowPublic(Court $court, string $date, string $tz): array
    {
        return $this->operatingWindow($court, $date, $tz);
    }

    private function operatingWindow(Court $court, string $date, string $tz): array
    {
        $dayOfWeek  = strtolower(Carbon::parse($date, $tz)->format('l'));
        $weekly     = $court->branch?->operating_hours ?? [];
        $hours      = $weekly[$dayOfWeek] ?? null;

        if ($hours && array_key_exists('is_open', $hours) && ! $hours['is_open']) {
            return ['07:00', '22:00', false];
        }

        return [$hours['open'] ?? '07:00', $hours['close'] ?? '22:00', true];
    }

    private function his($value): string
    {
        return $value instanceof Carbon
            ? $value->format('H:i:s')
            : substr((string) $value, 0, 8);
    }

    private function label(string $hm): string
    {
        return Carbon::createFromFormat('H:i', substr($hm, 0, 5))->format('g:i A');
    }

    private function toMinutes(string $hm): int
    {
        [$h, $m] = array_pad(explode(':', $hm), 2, 0);
        return ((int) $h) * 60 + (int) $m;
    }
}
