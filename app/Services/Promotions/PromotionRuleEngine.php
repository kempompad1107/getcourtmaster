<?php

namespace App\Services\Promotions;

use App\Models\Booking;
use App\Models\Court;
use App\Models\Promotion;
use Carbon\Carbon;

/**
 * Decides whether a Promotion applies to a given Court/Booking context and
 * computes the discount. Supports:
 *  - percentage / fixed base discount
 *  - hourly window (applicable_from_time / applicable_to_time)
 *  - day-of-week list (applicable_days)
 *  - court whitelist (applicable_courts)
 *  - holiday list (tenant_settings.holidays = ['YYYY-MM-DD',...])
 *  - bundle promo (type=bundle, value=N → "N for the price of N-1")
 */
class PromotionRuleEngine
{
    /**
     * Compute a discount for a *court rental* scenario.
     * Returns 0 if the promotion does not apply.
     */
    public function discountForCourt(Promotion $promo, Court $court, Carbon $start, Carbon $end, float $baseAmount): float
    {
        if (!$promo->isValid()) return 0;
        if (($promo->min_spend ?? 0) > 0 && $baseAmount < $promo->min_spend) return 0;

        if (!$this->matchesCourt($promo, $court))     return 0;
        if (!$this->matchesDayOfWeek($promo, $start)) return 0;
        if (!$this->matchesTimeWindow($promo, $start, $end)) return 0;
        if (!$this->matchesHoliday($promo, $start))   return 0;

        return $this->compute($promo, $baseAmount);
    }

    /**
     * Apply a promo to a POS/bundle scenario.
     * For `bundle` type promos, value=N means: for every N units bought of this product class,
     * one is free. Caller passes itemCount + unitPrice.
     */
    public function bundleDiscount(Promotion $promo, int $itemCount, float $unitPrice): float
    {
        if (!$promo->isValid()) return 0;
        if ($promo->type !== 'bundle') return 0;

        $n = max(1, (int) $promo->value);
        $freeItems = intdiv($itemCount, $n);
        return round($freeItems * $unitPrice, 2);
    }

    private function matchesCourt(Promotion $promo, Court $court): bool
    {
        $courts = $promo->applicable_courts ?? [];
        return empty($courts) || in_array($court->id, $courts);
    }

    private function matchesDayOfWeek(Promotion $promo, Carbon $start): bool
    {
        $days = $promo->applicable_days ?? [];
        if (empty($days)) return true;
        // 0=Sunday … 6=Saturday
        return in_array((int) $start->dayOfWeek, array_map('intval', $days), true);
    }

    private function matchesTimeWindow(Promotion $promo, Carbon $start, Carbon $end): bool
    {
        if (!$promo->applicable_from_time || !$promo->applicable_to_time) return true;
        $hh = $start->format('H:i');
        return $hh >= substr($promo->applicable_from_time, 0, 5)
            && $hh <  substr($promo->applicable_to_time,   0, 5);
    }

    private function matchesHoliday(Promotion $promo, Carbon $start): bool
    {
        // If the promo's name/code hints at "holiday", enforce match. Otherwise no-op.
        $hint = strtolower($promo->code . ' ' . $promo->name);
        if (!str_contains($hint, 'holiday')) return true;

        $holidays = (array) data_get($promo->tenant?->settings, 'holidays', []);
        return in_array($start->format('Y-m-d'), $holidays, true);
    }

    private function compute(Promotion $promo, float $amount): float
    {
        $discount = match ($promo->type) {
            'percentage' => $amount * ($promo->value / 100),
            'fixed'      => (float) $promo->value,
            default      => 0.0,
        };
        if ($promo->max_discount) {
            $discount = min($discount, (float) $promo->max_discount);
        }
        return round($discount, 2);
    }
}
