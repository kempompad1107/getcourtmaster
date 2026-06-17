<?php

namespace App\Services;

use App\Models\Court;
use Carbon\Carbon;

class PricingService
{
    public function calculate(Court $court, Carbon $start, Carbon $end, int $durationMinutes): array
    {
        $hours = $durationMinutes / 60;
        $rate = $court->getRateForSlot($start->toDateTime(), $end->toDateTime());
        $baseAmount = round($hours * $rate, 2);

        $tenant = $court->tenant;
        $taxRate = (float) ($tenant->getSetting('tax_rate', 0));
        $tax = round($baseAmount * ($taxRate / 100), 2);

        return [
            'rate' => $rate,
            'hours' => $hours,
            'base_amount' => $baseAmount,
            'tax' => $tax,
            'total' => $baseAmount + $tax,
        ];
    }

    public function calculateWithDiscount(
        Court $court,
        Carbon $start,
        Carbon $end,
        int $durationMinutes,
        float $discountAmount = 0
    ): array {
        $pricing = $this->calculate($court, $start, $end, $durationMinutes);
        $pricing['discount'] = $discountAmount;
        $pricing['total'] = max(0, $pricing['total'] - $discountAmount);
        return $pricing;
    }

    public function getAvailableSlots(Court $court, string $date, int $durationMinutes = 60, int $stepMinutes = 30): array
    {
        // Clamp to a sane allowed list so the slot window is always exactly the
        // duration the user picked.
        $allowed = [30, 60, 90, 120];
        $durationMinutes = in_array((int) $durationMinutes, $allowed, true)
            ? (int) $durationMinutes
            : 60;

        // Booking times are tenant-local wall-clock values. App timezone is UTC,
        // so we MUST anchor "now" to the tenant timezone, otherwise (e.g.) at 3 pm
        // Manila now() returns 7 am UTC and slots from 7 am onward all look future.
        $tz = $court->tenant?->timezone ?: config('app.timezone');

        // Operating hours come from the court's branch, per day of week. If the
        // branch is closed on that weekday, there are no slots to offer.
        $dayOfWeek = strtolower(Carbon::parse($date, $tz)->format('l'));
        $weeklyHours = $court->branch?->operating_hours ?? [];
        $branchHours = $weeklyHours[$dayOfWeek] ?? null;

        if ($branchHours && array_key_exists('is_open', $branchHours) && ! $branchHours['is_open']) {
            return [];
        }

        $openingTime = $branchHours['open']  ?? '07:00';
        $closingTime = $branchHours['close'] ?? '22:00';

        $dayStart = Carbon::parse($date . ' ' . $openingTime, $tz);
        $dayEnd   = Carbon::parse($date . ' ' . $closingTime, $tz);
        $now      = Carbon::now($tz);
        $isToday  = $now->toDateString() === Carbon::parse($date, $tz)->toDateString();

        // For today, skip any slot whose start is at or before "now". The slot
        // start must be STRICTLY in the future: at 3:00 pm, the 3:00 pm slot
        // is gone — earliest selectable is 3:30 pm.
        if ($isToday) {
            $earliest = $now->copy()->ceilMinute(30);
            // ceilMinute(30) leaves an already-aligned time unchanged, so push
            // one more boundary forward when we're exactly on a half-hour.
            if ($earliest->lte($now)) {
                $earliest->addMinutes(30);
            }
            if ($dayStart->lt($earliest)) {
                $dayStart = $earliest;
            }
        }

        $slots = [];

        // Normalise booked times to "HH:MM:SS" strings up front — comparing raw
        // Carbon instances against H:i:s strings is broken under Carbon 3 because
        // Carbon's __toString gives "Y-m-d H:i:s", and the string compare flips.
        $bookedSlots = $court->bookings()
            ->where('booking_date', $date)
            ->whereIn('status', ['pending', 'confirmed', 'active'])
            ->get(['start_time', 'end_time'])
            ->map(fn ($b) => [
                'start' => $b->start_time instanceof Carbon
                    ? $b->start_time->format('H:i:s')
                    : substr((string) $b->start_time, 0, 8),
                'end' => $b->end_time instanceof Carbon
                    ? $b->end_time->format('H:i:s')
                    : substr((string) $b->end_time, 0, 8),
            ]);

        $step = $stepMinutes > 0 ? $stepMinutes : 30;

        while ($dayStart->copy()->addMinutes($durationMinutes)->lte($dayEnd)) {
            $slotEnd  = $dayStart->copy()->addMinutes($durationMinutes);
            $slotS    = $dayStart->format('H:i:s');
            $slotE    = $slotEnd->format('H:i:s');

            $isBooked = $bookedSlots->contains(
                fn ($b) => $b['start'] < $slotE && $b['end'] > $slotS
            );

            if (!$isBooked) {
                $pricing = $this->calculate($court, $dayStart, $slotEnd, $durationMinutes);
                $slots[] = [
                    'start'       => $dayStart->format('H:i'),       // 24-h: form value
                    'end'         => $slotEnd->format('H:i'),
                    'start_label' => $dayStart->format('g:i A'),     // 12-h AM/PM: display
                    'end_label'   => $slotEnd->format('g:i A'),
                    'duration'    => $durationMinutes,
                    'rate'        => $pricing['rate'],
                    'total'       => $pricing['total'],
                    'available'   => true,
                ];
            }

            $dayStart->addMinutes($step);
        }

        return $slots;
    }
}
