<?php

namespace App\Jobs;

use App\Models\User;
use App\Services\ReportService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;

class GenerateReportExport implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 300;

    public function __construct(
        public readonly int $tenantId,
        public readonly string $reportType,
        public readonly string $from,
        public readonly string $to,
        public readonly int $userId,
    ) {}

    public function handle(ReportService $reportService): void
    {
        $data = match ($this->reportType) {
            'revenue' => $reportService->revenueSummary($this->tenantId, $this->from, $this->to),
            'occupancy' => $reportService->courtOccupancy($this->tenantId, $this->from, $this->to),
            'financial' => $reportService->financialSummary($this->tenantId, $this->from, $this->to),
            default => [],
        };

        // Reports contain sensitive financial data — always written to the
        // private disk, never the public/default (cloud) disk.
        $filename = "reports/{$this->tenantId}/{$this->reportType}_{$this->from}_{$this->to}.json";
        Storage::disk(config('filesystems.private'))->put($filename, json_encode($data));

        // Notify user
        $user = User::find($this->userId);
        $user?->notify(new \App\Notifications\ReportReadyNotification($filename));
    }
}
