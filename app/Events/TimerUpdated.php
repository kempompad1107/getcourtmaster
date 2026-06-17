<?php

namespace App\Events;

use App\Models\BookingTimer;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class TimerUpdated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public readonly BookingTimer $timer) {}

    public function broadcastOn(): Channel
    {
        return new Channel("court.{$this->timer->court_id}.timer");
    }

    public function broadcastAs(): string
    {
        return 'timer.updated';
    }

    public function broadcastWith(): array
    {
        return [
            'timer_id' => $this->timer->id,
            'status' => $this->timer->status,
            'elapsed_seconds' => $this->timer->elapsed_seconds_live,
            'remaining_seconds' => $this->timer->remaining_seconds,
            'is_overtime' => $this->timer->isOvertime(),
            'overtime_charge' => $this->timer->overtime_charge,
        ];
    }
}
