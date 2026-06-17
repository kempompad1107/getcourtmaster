<?php

namespace App\Observers;

use App\Models\Payment;
use App\Services\DashboardCache;

class PaymentObserver
{
    public function __construct(private readonly DashboardCache $cache) {}

    public function saved(Payment $payment): void
    {
        $this->cache->invalidateTenant((int) $payment->tenant_id);
    }

    public function deleted(Payment $payment): void
    {
        $this->cache->invalidateTenant((int) $payment->tenant_id);
    }

    public function restored(Payment $payment): void
    {
        $this->cache->invalidateTenant((int) $payment->tenant_id);
    }
}
