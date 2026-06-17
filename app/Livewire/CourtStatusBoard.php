<?php

namespace App\Livewire;

use App\Repositories\Contracts\CourtRepositoryInterface;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Component;

class CourtStatusBoard extends Component
{
    public ?int $tenantId = null;

    public function mount(?int $tenantId = null): void
    {
        $this->tenantId = $tenantId ?? auth()->user()?->tenant_id;
    }

    #[Computed]
    public function courts()
    {
        if (!$this->tenantId) {
            return collect();
        }

        return app(CourtRepositoryInterface::class)
            ->withTimers($this->tenantId);
    }

    #[On('echo:tenant.{tenantId}.courts,court.status.changed')]
    #[On('echo:tenant.{tenantId}.timers,timer.updated')]
    public function refreshBoard(): void
    {
        unset($this->courts);
    }

    public function getListeners(): array
    {
        return [
            "echo:tenant.{$this->tenantId}.courts,court.status.changed" => 'refreshBoard',
            "echo:tenant.{$this->tenantId}.timers,timer.updated" => 'refreshBoard',
        ];
    }

    public function render()
    {
        return view('livewire.court-status-board');
    }
}
