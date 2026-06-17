<?php

namespace App\Repositories\Contracts;

use App\Models\Booking;
use Illuminate\Support\Collection;

interface BookingRepositoryInterface extends BaseRepositoryInterface
{
    public function findByNumber(string $bookingNumber): ?Booking;

    public function forCustomer(int $customerId, array $statuses = []): Collection;

    public function forCourtBetween(int $courtId, string $date, string $startTime, string $endTime): Collection;

    public function upcomingForTenant(int $tenantId, int $limit = 10): Collection;

    public function calendarRange(int $tenantId, string $from, string $to): Collection;
}
