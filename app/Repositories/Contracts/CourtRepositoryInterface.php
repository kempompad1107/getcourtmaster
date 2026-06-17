<?php

namespace App\Repositories\Contracts;

use App\Models\Court;
use Illuminate\Support\Collection;

interface CourtRepositoryInterface extends BaseRepositoryInterface
{
    public function activeForTenant(int $tenantId): Collection;

    public function byStatus(int $tenantId, string $status): Collection;

    public function withTimers(int $tenantId): Collection;

    public function updateStatus(Court $court, string $status): Court;
}
