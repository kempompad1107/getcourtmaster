<?php

namespace App\Repositories\Eloquent;

use App\Events\CourtStatusChanged;
use App\Models\Court;
use App\Repositories\Contracts\CourtRepositoryInterface;
use Illuminate\Support\Collection;

class CourtRepository extends BaseRepository implements CourtRepositoryInterface
{
    public function model(): string
    {
        return Court::class;
    }

    public function activeForTenant(int $tenantId): Collection
    {
        return $this->forTenant($tenantId)
            ->where('status', '!=', 'closed')
            ->orderBy('name')
            ->get();
    }

    public function byStatus(int $tenantId, string $status): Collection
    {
        return $this->forTenant($tenantId)->where('status', $status)->get();
    }

    public function withTimers(int $tenantId): Collection
    {
        return $this->forTenant($tenantId)
            ->with([
                'bookings' => function ($q) {
                    $q->where('booking_date', today())
                      ->whereIn('status', ['active', 'confirmed'])
                      ->with('timer', 'customer');
                },
                'nextBookingToday.customer',
            ])
            ->get();
    }

    public function updateStatus(Court $court, string $status): Court
    {
        $previous = $court->status;
        $court->update(['status' => $status]);
        if ($previous !== $status) {
            event(new CourtStatusChanged($court));
        }
        return $court->fresh();
    }
}
