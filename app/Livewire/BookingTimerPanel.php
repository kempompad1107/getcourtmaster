<?php

namespace App\Livewire;

use App\Models\Booking;
use App\Services\BookingService;
use Livewire\Attributes\On;
use Livewire\Component;

class BookingTimerPanel extends Component
{
    public Booking $booking;

    /**
     * When the user clicks Stop and overtime is owed, we don't finalize the
     * booking yet — we hold the computed amount here and let staff choose
     * "Collect" or "Void" in a modal. Empty array means no pending decision.
     */
    public array $pendingOvertime = [];

    public function mount(Booking $booking): void
    {
        $this->booking = $booking->load('timer', 'court');
    }

    #[On('echo:court.{booking.court_id}.timer,timer.updated')]
    public function onTimerTick(): void
    {
        $this->booking->refresh();
    }

    public function start(BookingService $service): void
    {
        $this->authorize('update', $this->booking);
        $service->startTimer($this->booking);
        $this->booking->refresh();
    }

    public function pause(BookingService $service): void
    {
        $this->authorize('update', $this->booking);
        if ($timer = $this->booking->timer) {
            $service->pauseTimer($timer);
            $this->booking->refresh();
        }
    }

    public function resume(BookingService $service): void
    {
        $this->authorize('update', $this->booking);
        if ($timer = $this->booking->timer) {
            $service->resumeTimer($timer);
            $this->booking->refresh();
        }
    }

    public function extend(int $minutes, BookingService $service): void
    {
        $this->authorize('update', $this->booking);
        if ($timer = $this->booking->timer) {
            $service->extendTimer($timer, $minutes);
            $this->booking->refresh();
        }
    }

    public function stop(BookingService $service): void
    {
        $this->authorize('update', $this->booking);
        $timer = $this->booking->timer;
        if (!$timer) {
            return;
        }

        $overtime = $service->previewOvertimeAtStop($timer);

        // No overtime owed — finalize immediately.
        if ($overtime['charge'] <= 0) {
            $service->stopTimer($timer, 'auto');
            $this->booking->refresh();
            return;
        }

        // Overtime owed — surface the settlement modal instead of stopping.
        $this->pendingOvertime = $overtime;
    }

    public function collectOvertime(BookingService $service): void
    {
        $this->authorize('update', $this->booking);
        if (!$this->pendingOvertime || !$this->booking->timer) {
            return;
        }
        $service->stopTimer($this->booking->timer, 'collect', auth()->user());
        $this->pendingOvertime = [];
        $this->booking->refresh();
    }

    public function voidOvertime(BookingService $service): void
    {
        $this->authorize('update', $this->booking);
        if (!$this->pendingOvertime || !$this->booking->timer) {
            return;
        }
        $service->stopTimer($this->booking->timer, 'void', auth()->user());
        $this->pendingOvertime = [];
        $this->booking->refresh();
    }

    /**
     * No "cancel" path while overtime is owed — staff must pick Pay or Void.
     * Kept on the class so legacy callers don't 500, but a no-op.
     */
    public function cancelStop(): void
    {
        // Intentionally blank: the modal must end with a settlement decision.
    }

    public function render()
    {
        return view('livewire.booking-timer-panel');
    }
}
