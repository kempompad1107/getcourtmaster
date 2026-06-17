<?php

namespace App\Jobs;

use App\Services\BillingService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ProcessBillingRetries implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(BillingService $billing): void
    {
        $billing->generateRenewalInvoices();
        $billing->retryFailedInvoices();
        $billing->suspendOverdueTenants();
    }
}
