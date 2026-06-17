<?php

namespace App\Repositories\Eloquent;

use App\Models\Booking;
use App\Repositories\Contracts\BookingRepositoryInterface;
use Illuminate\Support\Collection;

class BookingRepository extends BaseRepository implements BookingRepositoryInterface
{
    public function model(): string
    {
        return Booking::class;
    }

    public function findByNumber(string $bookingNumber): ?Booking
    {
        return $this->query()->where('booking_number', $bookingNumber)->first();
    }

    public function forCustomer(int $customerId, array $statuses = []): Collection
    {
        $q = $this->query()->where('customer_id', $customerId);
        if (!empty($statuses)) {
            $q->whereIn('status', $statuses);
        }
        return $q->latest('booking_date')->get();
    }

    public function forCourtBetween(int $courtId, string $date, string $startTime, string $endTime): Collection
    {
        return $this->query()
            ->where('court_id', $courtId)
            ->where('booking_date', $date)
            ->whereIn('status', ['pending', 'confirmed', 'active'])
            ->where(function ($q) use ($startTime, $endTime) {
                $q->where('start_time', '<', $endTime)
                  ->where('end_time', '>', $startTime);
            })
            ->get();
    }

    public function upcomingForTenant(int $tenantId, int $limit = 10): Collection
    {
        return $this->forTenant($tenantId)
            ->upcoming()
            ->orderBy('booking_date')
            ->orderBy('start_time')
            ->limit($limit)
            ->get();
    }

    public function calendarRange(int $tenantId, string $from, string $to): Collection
    {
        return $this->forTenant($tenantId)
            ->whereBetween('booking_date', [$from, $to])
            ->with(['court', 'customer'])
            ->get();
    }
}
