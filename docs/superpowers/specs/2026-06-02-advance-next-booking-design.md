# Advance Next Booking on Early Stop

**Date:** 2026-06-02  
**Status:** Approved

## Problem

When a court session ends early, the next booking on that court sits idle until its original scheduled time. Staff have no way to pull it forward to use the freed time, which wastes court availability.

## Solution

After stopping a session early, the stop-timer flow gains a second step: a prompt that shows the next booking and offers staff the option to advance it to start immediately. Only the immediate next booking is affected. Staff must confirm — nothing happens automatically.

## Data Flow

1. Staff stops a session early (e.g. Booking A stops at 2:06 PM, originally scheduled until 2:28 PM).
2. The stop-timer API response now includes a `next_booking` object if one exists on the same court and date starting after the actual stop time.
3. The stop-timer modal detects `next_booking` in the response and shows a second step instead of closing.
4. Staff taps **Advance Now** → new endpoint shifts the next booking's times, preserves duration exactly, sends customer notification → modal closes with success message.
5. Staff taps **Skip** (or closes modal) → next booking keeps its original times, modal closes.

**Duration preservation example:**
- Booking A stopped at 2:06 PM
- Booking B was 2:28 PM – 2:58 PM (30 min)
- After advance: Booking B becomes 2:06 PM – 2:36 PM (still 30 min)

No pricing recalculation. The customer booked and paid for a fixed duration; shifting it earlier doesn't change what they owe.

## Backend Components

### `BookingService::findNextBooking(Booking $stopped, Carbon $freedAt): ?Booking`

- Queries same `court_id`, same `booking_date`
- Status in `['pending', 'confirmed']`
- `start_time > $freedAt` (H:i:s comparison)
- Orders by `start_time ASC`, returns first or null

### `BookingService::advanceBooking(Booking $next, Carbon $newStart): Booking`

- Computes original duration: `Carbon::parse(end_time)->diffInMinutes(Carbon::parse(start_time))`
- Sets `start_time = $newStart->format('H:i')`
- Sets `end_time = $newStart->copy()->addMinutes($duration)->format('H:i')`
- Updates `duration_minutes` (unchanged, but recomputed for safety)
- Fires `BookingAdvancedNotification` to `$next->customer`
- Returns updated booking

### Route & Controller

```
POST /admin/bookings/{booking}/advance
```

- Controller: `Admin\BookingController@advance`
- Validates that `$booking->status` is `pending` or `confirmed`
- Resolves `$freedAt` from the request (ISO datetime string sent by the UI)
- Calls `advanceBooking()`, returns updated booking as JSON

### `BookingAdvancedNotification` (new)

- Sent to the booking's customer
- Content: "Your booking on [date] has been moved from [old start] to [new start]. Your session duration is unchanged."
- Delivery: database (in-app) + mail (if customer has email)

## UI — Stop-Timer Modal

The existing stop-timer modal on the status board (and anywhere else stop is triggered) is extended:

**Normal close path (no next booking):** Modal closes as it does today.

**Extended path (next booking found):**
After the stop completes (including any overtime collect/void step), instead of closing, the modal transitions to:

```
╔═══════════════════════════════════════════╗
║  Session ended at 2:06 PM                 ║
║                                           ║
║  Next booking on this court:              ║
║  Juan Dela Cruz — 2:28 PM to 2:58 PM      ║
║                                           ║
║  Advance to 2:06 PM – 2:36 PM?            ║
║                                           ║
║  [ Advance Now ]      [ Skip ]            ║
╚═══════════════════════════════════════════╝
```

- **Advance Now**: POST to `/admin/bookings/{id}/advance` with `freed_at` = actual stop time. On success, shows "Booking advanced — customer notified" then closes.
- **Skip**: Closes modal immediately. Next booking unchanged.
- **Escape / backdrop click**: Treated as Skip.

The `freed_at` value comes from the stop-timer response (`timer.stopped_at`), not from `now()` at click time, so there's no drift between when the session ended and when staff clicks the button.

## Files to Change

| File | Change |
|------|--------|
| `app/Services/BookingService.php` | Add `findNextBooking()` and `advanceBooking()` |
| `app/Http/Controllers/Admin/BookingController.php` | Add `advance()` action; extend stop response to include `next_booking` |
| `app/Notifications/BookingAdvancedNotification.php` | New notification class |
| `routes/web.php` (admin group) | Add `POST bookings/{booking}/advance` route |
| `resources/views/admin/courts/status-board.blade.php` | Extend stop-timer JS to handle `next_booking` response and render the second step |

## Constraints & Non-Goals

- Only the **immediate next booking** is advanced — no cascade.
- No pricing recalculation on advance.
- Walk-in bookings on the same court are included candidates (they have `confirmed` status once started, so they wouldn't appear; but a walk-in booked ahead would be `confirmed`).
- Auto-stop-after-grace path does **not** trigger this prompt (unattended, no staff present to confirm).
- If the next booking's customer has no email, only the in-app notification is sent — no error raised.

## Edge Cases

**Overlap guard:** Before advancing, `advanceBooking()` must call `BookingService::checkAvailability()` for the new `[newStart, newStart+duration]` window (excluding the booking being moved). If another booking was inserted into the gap since the stop, the advance is rejected with a validation error and the UI shows "Cannot advance — a conflict was detected."

**Same-start guard:** If `newStart >= $next->start_time` (i.e., session didn't actually free any time before the next booking), `findNextBooking()` returns null and no prompt is shown.

**Next booking already active/completed:** The `advance()` controller validates that the target booking is `pending` or `confirmed`. If it's already `active` or `completed`, the action is a no-op and returns an error.
