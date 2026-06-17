# Advance Next Booking on Early Stop — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** When a court session ends early, show staff a prompt to pull the next booking on that court forward to start immediately, preserving the booked duration and notifying the customer.

**Architecture:** Two new service methods on `BookingService` handle finding the next booking and advancing it. The `handleStopWithSettlement()` controller helper is extended to include `next_booking` data in every successful stop response. The status-board Alpine JS `stop()` flow gains a second step: if the response has `next_booking`, dispatch an event to a new inline modal instead of reloading immediately. The modal calls a new `POST /admin/bookings/{booking}/advance` endpoint, then reloads.

**Tech Stack:** Laravel 10, Alpine.js v3, Bootstrap 5, Carbon, Laravel Notifications (database + mail).

> **Note on tests:** The project test suite has a pre-existing SQLite/ENUM migration failure that breaks `php artisan test` (~61 tests). Verification steps in this plan use `php artisan tinker` instead. Do not attempt to fix the test suite as part of this feature.

---

## File Map

| File | Action | What changes |
|------|--------|-------------|
| `app/Services/BookingService.php` | Modify | Add `findNextBooking()` and `advanceBooking()` |
| `app/Notifications/BookingAdvancedNotification.php` | Create | New notification sent to the customer |
| `app/Http/Controllers/Admin/BookingController.php` | Modify | Add `advance()` action, `nextBookingAfterStop()` helper, extend stop responses |
| `routes/web.php` | Modify | Register `POST /admin/bookings/{booking}/advance` |
| `resources/views/admin/courts/status-board.blade.php` | Modify | Add advance modal HTML + Alpine component; update `stop()` to show modal |

---

## Task 1 — Service: `findNextBooking()` and `advanceBooking()`

**Files:**
- Modify: `app/Services/BookingService.php`

- [ ] **Step 1: Add `findNextBooking()` after `autoStopExpiredTimers()`**

Open `app/Services/BookingService.php`. After the closing `}` of `autoStopExpiredTimers()` (currently around line 1609), insert:

```php
/**
 * Find the next booking on the same court and date that starts after
 * $freedAt. Used by the stop-timer flow to offer the advance prompt.
 * Returns null when there is nothing to advance.
 */
public function findNextBooking(Booking $stopped, Carbon $freedAt): ?Booking
{
    return Booking::where('court_id', $stopped->court_id)
        ->where('booking_date', $stopped->booking_date->format('Y-m-d'))
        ->whereIn('status', ['pending', 'confirmed'])
        ->where('start_time', '>', $freedAt->format('H:i:s'))
        ->orderBy('start_time')
        ->with('customer:id,name')
        ->first();
}
```

- [ ] **Step 2: Add `advanceBooking()` immediately after `findNextBooking()`**

```php
/**
 * Shift $next's start and end times earlier to $newStart, preserving
 * duration exactly. Throws ValidationException if a conflict is detected
 * or the booking is no longer in an advanceable state.
 */
public function advanceBooking(Booking $next, Carbon $newStart): Booking
{
    if (!in_array($next->status, ['pending', 'confirmed'], true)) {
        throw ValidationException::withMessages([
            'advance' => 'This booking cannot be advanced.',
        ]);
    }

    $dateStr       = $next->booking_date->format('Y-m-d');
    $originalStart = Carbon::parse($dateStr . ' ' . Carbon::parse($next->start_time)->format('H:i'));
    $originalEnd   = Carbon::parse($dateStr . ' ' . Carbon::parse($next->end_time)->format('H:i'));
    $duration      = (int) $originalStart->diffInMinutes($originalEnd);

    $newEnd = $newStart->copy()->addMinutes($duration);

    if (!$this->checkAvailability(
        $next->court_id,
        $dateStr,
        $newStart->format('H:i'),
        $newEnd->format('H:i'),
        $next->id
    )) {
        throw ValidationException::withMessages([
            'advance' => 'Cannot advance — a conflict was detected.',
        ]);
    }

    $oldStart = Carbon::parse($next->start_time)->format('g:i A');
    $oldEnd   = Carbon::parse($next->end_time)->format('g:i A');

    $next->update([
        'start_time'       => $newStart->format('H:i'),
        'end_time'         => $newEnd->format('H:i'),
        'duration_minutes' => $duration,
    ]);

    $next->customer?->notify(new \App\Notifications\BookingAdvancedNotification(
        $next->fresh(),
        $oldStart,
        $oldEnd,
    ));

    return $next->fresh();
}
```

- [ ] **Step 3: Verify via tinker**

```bash
php artisan tinker
```

```php
// Paste and run in tinker — adjust IDs to real rows in your DB.
// Pick a completed booking whose court has a pending/confirmed booking later today.
$stopped  = App\Models\Booking::find(STOPPED_BOOKING_ID);
$freedAt  = now()->subMinutes(5); // simulate stopping 5 min ago
$service  = app(App\Services\BookingService::class);
$next     = $service->findNextBooking($stopped, $freedAt);
// Should return a Booking if one exists, null otherwise.
dd($next?->booking_number, $next?->start_time);
```

Expected: booking number and original start time of the next booking, or `null` if none.

- [ ] **Step 4: Commit**

```bash
git add app/Services/BookingService.php
git commit -m "feat: add findNextBooking and advanceBooking to BookingService"
```

---

## Task 2 — Notification: `BookingAdvancedNotification`

**Files:**
- Create: `app/Notifications/BookingAdvancedNotification.php`

- [ ] **Step 1: Create the file**

```php
<?php

namespace App\Notifications;

use App\Models\Booking;
use App\Notifications\Concerns\HonorsUserChannelPreferences;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class BookingAdvancedNotification extends Notification
{
    use Queueable, HonorsUserChannelPreferences;

    public function __construct(
        public readonly Booking $booking,
        public readonly string $oldStart,
        public readonly string $oldEnd,
    ) {}

    public function via(object $notifiable): array
    {
        return $this->channelsForUser($notifiable);
    }

    public function toWebPush(object $notifiable): array
    {
        $newStart = Carbon::parse($this->booking->start_time)->format('g:i A');

        return [
            'title' => 'Your booking time has been updated',
            'body'  => "#{$this->booking->booking_number} • Now starts at {$newStart}",
            'url'   => url('/app/bookings/' . $this->booking->id),
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $newStart = Carbon::parse($this->booking->start_time)->format('g:i A');
        $newEnd   = Carbon::parse($this->booking->end_time)->format('g:i A');

        return (new MailMessage)
            ->subject('Booking time updated — ' . $this->booking->booking_number)
            ->greeting('Hi ' . $notifiable->name . ',')
            ->line('Your booking has been moved to an earlier time.')
            ->line('Booking: **' . $this->booking->booking_number . '**')
            ->line('Court: **' . ($this->booking->court->name ?? '—') . '**')
            ->line('Date: **' . $this->booking->booking_date?->format('F j, Y') . '**')
            ->line('New time: **' . $newStart . ' – ' . $newEnd . '**')
            ->line('(Previously: ' . $this->oldStart . ' – ' . $this->oldEnd . ')')
            ->line('Your session duration is unchanged.')
            ->action('View Booking', url('/app/bookings/' . $this->booking->id));
    }

    public function toArray(object $notifiable): array
    {
        $newStart = Carbon::parse($this->booking->start_time)->format('g:i A');

        return [
            'booking_id'     => $this->booking->id,
            'booking_number' => $this->booking->booking_number,
            'message'        => "Your booking time was moved earlier — now starts at {$newStart}",
            'type'           => 'booking_advanced',
            'url'            => url('/app/bookings/' . $this->booking->id),
        ];
    }
}
```

- [ ] **Step 2: Verify the class loads**

```bash
php artisan tinker
```

```php
$notif = new App\Notifications\BookingAdvancedNotification(
    App\Models\Booking::first(),
    '2:28 PM',
    '2:58 PM'
);
echo get_class($notif); // App\Notifications\BookingAdvancedNotification
```

Expected: class name printed with no errors.

- [ ] **Step 3: Commit**

```bash
git add app/Notifications/BookingAdvancedNotification.php
git commit -m "feat: add BookingAdvancedNotification"
```

---

## Task 3 — Controller: `advance()` action + extend stop responses + route

**Files:**
- Modify: `app/Http/Controllers/Admin/BookingController.php`
- Modify: `routes/web.php`

- [ ] **Step 1: Add `use Carbon\Carbon;` import to the controller**

Open `app/Http/Controllers/Admin/BookingController.php`. After the existing `use` block (around line 12), add:

```php
use Carbon\Carbon;
```

The full import block should look like:

```php
use App\Http\Controllers\Controller;
use App\Http\Requests\BookingRequest;
use App\Models\Booking;
use App\Models\BookingTimer;
use App\Models\Court;
use App\Models\User;
use App\Services\BookingService;
use Carbon\Carbon;
use Illuminate\Http\Request;
```

- [ ] **Step 2: Add `nextBookingAfterStop()` private helper**

Inside `BookingController`, after the closing `}` of `handleStopWithSettlement()` (currently around line 302), add:

```php
/**
 * After a session has been stopped, look up the next booking on the same
 * court that starts after the actual stop time. Returns a shape ready for
 * the frontend advance-modal, or null when there is nothing to advance.
 */
private function nextBookingAfterStop(BookingTimer $timer): ?array
{
    $booking = $timer->booking;
    if (!$booking) {
        return null;
    }

    $booking->load('customer');

    // Convert stopped_at (UTC) to the tenant's wall-clock timezone so that
    // the H:i:s comparison against DB start_time (also wall-clock) is correct.
    $tz      = $booking->tenant?->timezone ?: config('app.timezone');
    $freedAt = ($timer->stopped_at ?? now())->copy()->setTimezone($tz);

    $next = $this->bookingService->findNextBooking($booking, $freedAt);
    if (!$next) {
        return null;
    }

    return [
        'id'             => $next->id,
        'booking_number' => $next->booking_number,
        'customer_name'  => $next->customer?->name ?? 'Walk-in',
        'old_start'      => Carbon::parse($next->start_time)->format('g:i A'),
        'old_end'        => Carbon::parse($next->end_time)->format('g:i A'),
        'new_start'      => $freedAt->format('g:i A'),
        'new_end'        => $freedAt->copy()->addMinutes($next->duration_minutes)->format('g:i A'),
        'freed_at'       => $freedAt->toIso8601String(),
    ];
}
```

- [ ] **Step 3: Extend the "no overtime" stop response in `handleStopWithSettlement()`**

Find this block in `handleStopWithSettlement()` (around line 282):

```php
            // No overtime — stop normally.
            $timer = $this->bookingService->stopTimer($timer, 'auto');

            return response()->json([
                'message'         => 'Session stopped.',
                'timer'           => $timer,
                'overtime_charge' => $timer->overtime_charge,
            ]);
```

Replace it with:

```php
            // No overtime — stop normally.
            $timer = $this->bookingService->stopTimer($timer, 'auto');

            return response()->json([
                'message'         => 'Session stopped.',
                'timer'           => $timer,
                'overtime_charge' => $timer->overtime_charge,
                'next_booking'    => $this->nextBookingAfterStop($timer),
            ]);
```

- [ ] **Step 4: Extend the settlement stop response in `handleStopWithSettlement()`**

Find this block (around line 292):

```php
        $timer = $this->bookingService->stopTimer($timer, $settlement, $request->user());

        return response()->json([
            'message'         => $settlement === 'collect'
                ? 'Overtime collected. Session closed.'
                : 'Overtime voided. Session closed.',
            'timer'           => $timer,
            'overtime_charge' => $timer->overtime_charge,
            'settlement'      => $timer->overtime_settlement,
        ]);
```

Replace it with:

```php
        $timer = $this->bookingService->stopTimer($timer, $settlement, $request->user());

        return response()->json([
            'message'         => $settlement === 'collect'
                ? 'Overtime collected. Session closed.'
                : 'Overtime voided. Session closed.',
            'timer'           => $timer,
            'overtime_charge' => $timer->overtime_charge,
            'settlement'      => $timer->overtime_settlement,
            'next_booking'    => $this->nextBookingAfterStop($timer),
        ]);
```

- [ ] **Step 5: Add the `advance()` controller action**

After the `reschedule()` method (around line 357), add:

```php
/**
 * Advance a pending/confirmed booking to start at $freed_at (the moment
 * the previous session actually ended). Duration is preserved exactly.
 * Called by the status-board's advance-next modal after a stop.
 */
public function advance(Request $request, Booking $booking)
{
    $this->authorize('update', $booking);

    $data = $request->validate([
        'freed_at' => 'required|date',
    ]);

    if (!in_array($booking->status, ['pending', 'confirmed'], true)) {
        return response()->json(['message' => 'This booking cannot be advanced.'], 422);
    }

    // Convert the UTC ISO timestamp from the frontend to the tenant's wall-clock
    // timezone so H:i formatting matches the DB start_time (wall-clock) values.
    $tz      = $booking->tenant?->timezone ?: config('app.timezone');
    $freedAt = Carbon::parse($data['freed_at'])->setTimezone($tz);

    $updated = $this->bookingService->advanceBooking($booking, $freedAt);

    return response()->json([
        'message' => 'Booking advanced — customer notified.',
        'booking' => $updated,
    ]);
}
```

- [ ] **Step 6: Register the route in `routes/web.php`**

Open `routes/web.php`. Find the existing booking routes block (around line 188):

```php
        Route::post('/bookings/{booking}/collect-cash', [BookingController::class, 'collectCash'])->name('bookings.collect-cash')->middleware('branch.required');
```

Add the new route directly after that line:

```php
        Route::post('/bookings/{booking}/advance',      [BookingController::class, 'advance'])->name('bookings.advance')->middleware('branch.required');
```

- [ ] **Step 7: Verify via tinker**

```bash
php artisan tinker
```

```php
// Confirm the route is registered.
$route = collect(app('router')->getRoutes())->first(fn($r) => $r->getName() === 'admin.bookings.advance');
echo $route ? 'FOUND: ' . $route->uri() : 'MISSING';
// Expected: FOUND: admin/bookings/{booking}/advance
```

```php
// Confirm advanceBooking() works end-to-end (no notification sent to real customer in tinker).
// Pick a confirmed booking on a court that has no conflicting booking at the target time.
$booking = App\Models\Booking::where('status', 'confirmed')->latest()->first();
$service = app(App\Services\BookingService::class);
$freedAt = now()->subHour(); // an hour ago — should be empty on the court
$updated = $service->advanceBooking($booking, $freedAt);
echo $updated->start_time; // should equal $freedAt->format('H:i')
```

Expected: updated `start_time` matches `$freedAt->format('H:i')`.

- [ ] **Step 8: Commit**

```bash
git add app/Http/Controllers/Admin/BookingController.php routes/web.php
git commit -m "feat: add advance() action and extend stop responses with next_booking"
```

---

## Task 4 — UI: Advance-next modal on the status board

**Files:**
- Modify: `resources/views/admin/courts/status-board.blade.php`

- [ ] **Step 1: Add the advance modal HTML**

Open `resources/views/admin/courts/status-board.blade.php`. Find the overtime settlement modal closing `</div>` (the outer `</div>` that closes the `x-data="overtimeSettlementModal()"` block, currently around line 202):

```html
    </div>
</div>

@endsection
```

Insert the new modal HTML between the closing overtime modal `</div>` and the `</div>` that closes the root `x-data="courtBoard()"`:

```html
    {{-- Advance-next-booking modal — shown after an early stop when a
         subsequent booking exists on the same court. --}}
    <div
        x-data="advanceNextModal()"
        :class="open ? 'd-flex' : 'd-none'"
        @open-advance-modal.window="show($event.detail)"
        style="position:fixed;inset:0;z-index:1090;align-items:center;justify-content:center;background:rgba(15,23,42,0.55);backdrop-filter:blur(2px)"
        role="alertdialog" aria-modal="true"
    >
        <div class="card shadow-lg" style="width:100%;max-width:480px;margin:1rem">
            <div class="card-body">
                <div class="d-flex align-items-start gap-2 mb-3">
                    <i class="bi bi-fast-forward-fill text-success fs-4"></i>
                    <div class="flex-grow-1">
                        <h6 class="mb-1">Advance Next Booking?</h6>
                        <div class="small text-muted">Court is now free earlier than expected</div>
                    </div>
                </div>

                <template x-if="nextBooking">
                    <div>
                        <div class="border rounded p-3 mb-3 bg-light">
                            <div class="d-flex justify-content-between mb-1">
                                <span class="small text-muted">Customer</span>
                                <span class="small fw-medium" x-text="nextBooking.customer_name"></span>
                            </div>
                            <div class="d-flex justify-content-between mb-1">
                                <span class="small text-muted">Scheduled</span>
                                <span class="small" x-text="nextBooking.old_start + ' – ' + nextBooking.old_end"></span>
                            </div>
                            <div class="d-flex justify-content-between">
                                <span class="small text-muted fw-semibold">Advance to</span>
                                <span class="small fw-bold text-success" x-text="nextBooking.new_start + ' – ' + nextBooking.new_end"></span>
                            </div>
                        </div>
                        <p class="small text-muted mb-3">
                            Duration stays the same. The customer will be notified of the new time.
                        </p>
                    </div>
                </template>

                <div class="d-flex justify-content-end gap-2">
                    <button type="button" class="btn btn-outline-secondary btn-sm"
                            :disabled="busy"
                            @click="skip()">Skip</button>
                    <button type="button" class="btn btn-success btn-sm"
                            :disabled="busy"
                            @click="advance()">
                        <span x-show="!busy">Advance Now</span>
                        <span x-show="busy">…</span>
                    </button>
                </div>
            </div>
        </div>
    </div>
```

The structure should now be:

```html
    {{-- existing overtime modal ... --}}
    </div>

    {{-- new advance modal ... --}}
    </div>

</div>  ← closes x-data="courtBoard()"

@endsection
```

- [ ] **Step 2: Update the `stop()` method in `courtCard` to handle `next_booking`**

Find the `stop()` method in the `<script>` block (around line 403). The current successful-stop block is:

```javascript
                if (this.interval) clearInterval(this.interval);
                publishTimer({ type: 'stop', timerId: this.timerId, courtId: this.courtId });
                window.location.reload();
```

Replace those three lines with:

```javascript
                if (this.interval) clearInterval(this.interval);
                publishTimer({ type: 'stop', timerId: this.timerId, courtId: this.courtId });

                if (d.next_booking) {
                    this.busy = false;
                    window.dispatchEvent(new CustomEvent('open-advance-modal', {
                        detail: { nextBooking: d.next_booking },
                    }));
                    return;
                }

                window.location.reload();
```

- [ ] **Step 3: Also handle `next_booking` after the overtime settlement modal settles**

The overtime settlement modal calls `this.onSettle(choice)` which calls `this.stop(choice)` on the courtCard. The `stop(choice)` call with a `settlement` argument goes through the same successful-stop block you already patched in Step 2 — no additional change needed here.

However, the `overtimeSettlementModal.settle()` method currently does:

```javascript
            try {
                await this.onSettle(choice);
                // Caller reloads the page on success; we just close defensively.
                this.open = false;
```

The "caller reloads the page" comment is now only conditionally true (when no next_booking). The behavior is still correct — if there IS a next_booking, the courtCard's stop() will dispatch open-advance-modal before returning, and the settle() method's `this.open = false` will close the overtime modal. No change needed to `overtimeSettlementModal`.

- [ ] **Step 4: Add the `advanceNextModal()` Alpine component**

At the end of the `<script>` block, after the closing `}` of `overtimeSettlementModal()`, add:

```javascript
function advanceNextModal() {
    return {
        open:        false,
        busy:        false,
        nextBooking: null,

        show(detail) {
            this.nextBooking = detail.nextBooking || null;
            this.open        = true;
        },

        async advance() {
            if (this.busy || !this.nextBooking) return;
            this.busy = true;
            try {
                const r = await fetch(`${window.APP_BASE}/admin/bookings/${this.nextBooking.id}/advance`, {
                    method:  'POST',
                    headers: {
                        'Content-Type':      'application/json',
                        'Accept':            'application/json',
                        'X-Requested-With':  'XMLHttpRequest',
                        'X-CSRF-TOKEN':      CSRF,
                    },
                    body: JSON.stringify({ freed_at: this.nextBooking.freed_at }),
                });
                const d = await r.json().catch(() => ({}));
                if (!r.ok) {
                    alert(d.message || `Advance failed (${r.status})`);
                    return;
                }
                this.open = false;
                window.location.reload();
            } catch (e) {
                alert('Network error: ' + e.message);
            } finally {
                this.busy = false;
            }
        },

        skip() {
            this.open = false;
            window.location.reload();
        },
    };
}
```

- [ ] **Step 5: Manual smoke test**

1. Open the status board at `/admin/courts/status-board`.
2. Start a walk-in session on a court that already has another booking later today (e.g., at 2:28 PM).
3. Stop the session early (e.g., at 2:06 PM).
4. Confirm the advance modal appears showing the next booking's old/new times.
5. Click **Advance Now** — verify the page reloads and the next booking's "Next Booking" chip on the card now shows the advanced time.
6. Check the customer's notifications (in-app) to confirm the `BookingAdvancedNotification` was created.
7. Repeat and click **Skip** instead — verify the page reloads and the next booking keeps its original time.

- [ ] **Step 6: Commit**

```bash
git add resources/views/admin/courts/status-board.blade.php
git commit -m "feat: advance-next-booking prompt in stop-timer flow on status board"
```

---

## Done

All four tasks complete. The feature is live:

- `BookingService::findNextBooking()` — queries the next advanceable booking
- `BookingService::advanceBooking()` — shifts times, guards conflicts, notifies customer
- `BookingAdvancedNotification` — in-app + email to customer
- `POST /admin/bookings/{booking}/advance` — controller action
- Stop-timer response includes `next_booking` on successful stop
- Status-board modal flow: stop → (advance prompt if next booking exists) → reload
