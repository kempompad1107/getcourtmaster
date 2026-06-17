<?php

namespace App\Events;

use App\Models\Court;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class CourtStatusChanged implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public readonly Court $court) {}

    public function broadcastOn(): Channel
    {
        return new Channel("tenant.{$this->court->tenant_id}.courts");
    }

    public function broadcastAs(): string
    {
        return 'court.status.changed';
    }

    public function broadcastWith(): array
    {
        return [
            'court_id' => $this->court->id,
            'name' => $this->court->name,
            'status' => $this->court->status,
            'status_color' => $this->court->status_color,
        ];
    }
}
