<?php

namespace App\Services;

use App\Events\BookingCancelled;
use App\Events\BookingConfirmed;
use App\Events\BookingCreated;
use App\Events\TimerUpdated;
use App\Models\Booking;
use App\Models\BookingTimer;
use App\Models\Branch;
use App\Models\Court;
use App\Models\MembershipTransaction;
use App\Models\Payment;
use App\Models\Promotion;
use App\Models\RefundRequest;
use App\Models\Tenant;
use App\Models\User;
use App\Notifications\BookingApprovalRequiredNotification;
use App\Notifications\BookingApprovedNotification;
use App\Notifications\BookingCancelledNotification;
use App\Notifications\BookingDeniedNotification;
use App\Notifications\BookingPaidNotification;
use App\Notifications\BookingPendingApprovalNotification;
use App\Notifications\BookingRescheduledNotification;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class BookingService
{
    private function parseBookingDateTime(?string $date, ?string $timeOrDateTime, string $fieldName): Carbon
    {
        if (blank($timeOrDateTime)) {
            throw ValidationException::withMessages([
                $fieldName => "The {$fieldName} field is required.",
            ]);
        }

        try {
            // Full datetime provided (e.g., "2026-05-21 08:00:00"): parse directly.
            if (preg_match('/^\d{4}-\d{2}-\d{2}\s+\d{2}:\d{2}(:\d{2})?$/', $timeOrDateTime)) {
                return Carbon::parse($timeOrDateTime);
            }

            // Time-only provided (e.g., "08:00" or "08:00:00"): requires booking date.
            if (preg_match('/^\d{2}:\d{2}(:\d{2})?$/', $timeOrDateTime)) {
                if (blank($date)) {
                    throw ValidationException::withMessages([
                        'booking_date' => 'The booking_date field is required when time-only values are provided.',
                    ]);
                }

                return Carbon::parse($date . ' ' . $timeOrDateTime);
            }

            // Fallback for parseable values while still providing controlled validation errors.
            return Carbon::parse($timeOrDateTime);
        } catch (\Throwable $e) {
            throw ValidationException::withMessages([
                $fieldName => "Invalid datetime format for {$fieldName}.",
            ]);
        }
    }

    public function __construct(
        private readonly PricingService $pricingService,
        private readonly QrCodeService $qrCodeService,
        private readonly WalletService $walletService,
        private readonly AvailabilityService $availability,
        private readonly ?\App\Services\Promotions\PromotionRuleEngine $promoEngine = null,
    ) {}

    public function checkAvailability(int $courtId, string $date, string $startTime, string $endTime, ?int $exceptBookingId = null): bool
    {
        return !Booking::where('court_id', $courtId)
            ->where('booking_date', $date)
            ->whereIn('status', ['pending', 'confirmed', 'active'])
            ->when($exceptBookingId, fn ($q) => $q->where('id', '!=', $exceptBookingId))
            ->where(function ($q) use ($startTime, $endTime) {
                $q->where(function ($inner) use ($startTime, $endTime) {
                    $inner->where('start_time', '<', $endTime)
                          ->where('end_time', '>', $startTime);
                });
            })
            ->exists();
    }

    public function create(array $data, ?User $customer, ?User $createdBy = null): Booking
    {
        return DB::transaction(function () use ($data, $customer, $createdBy) {
            // Concurrency guard (BOOK-01): take a row lock on the court for the
            // life of this transaction. Concurrent create() calls for the same
            // court serialize here, so the availability check below always runs
            // after any in-flight booking for this court has committed — closing
            // the check-then-insert race that allowed double-booking.
            $court = Court::whereKey($data['court_id'])->lockForUpdate()->firstOrFail();

            // Anonymous booking: fall back to the per-tenant Walk-in Guest user so
            // the rest of the pipeline (referral logic, wallet credits, customer
            // relations) can keep treating the booking as having a customer.
            if ($customer === null) {
                if ($createdBy === null) {
                    throw ValidationException::withMessages([
                        'customer_id' => 'A customer or staff context is required to create a booking.',
                    ]);
                }
                $customer = $this->getOrCreateWalkInGuest($createdBy);
            }

            if (!$this->checkAvailability($court->id, $data['booking_date'], $data['start_time'], $data['end_time'])) {
                throw ValidationException::withMessages(['time_slot' => 'This time slot is already booked.']);
            }

            $start = $this->parseBookingDateTime($data['booking_date'] ?? null, $data['start_time'] ?? null, 'start_time');
            $end = $this->parseBookingDateTime($data['booking_date'] ?? null, $data['end_time'] ?? null, 'end_time');

            // Reject "online" bookings whose start time has already arrived. The slot
            // must be strictly in the future — at 3:00 pm, the 3:00 pm slot is gone.
            // Walk-ins are exempt (they're physically at the desk right now).
            // Compare in tenant timezone: TIME columns are wall-clock values, not UTC.
            $type = $data['type'] ?? 'online';
            $tz = $court->tenant?->timezone ?: config('app.timezone');
            $startTz = Carbon::parse(($data['booking_date'] ?? '') . ' ' . ($data['start_time'] ?? ''), $tz);
            if ($type !== 'walk_in' && $startTz->lte(Carbon::now($tz))) {
                throw ValidationException::withMessages([
                    'start_time' => 'That time slot has already started. Please pick a future time.',
                ]);
            }

            // Enforce the venue's advance-booking horizon for customer-made
            // bookings (staff may book further out). Disabled when the setting
            // is unset/0, so behaviour is unchanged until a venue configures it.
            $bookedByStaff = $createdBy && in_array($createdBy->user_type, ['business_owner', 'staff'], true);
            if ($type !== 'walk_in' && !$bookedByStaff) {
                $maxDays = (int) ($court->tenant?->getSetting('advance_booking_days', 0));
                if ($maxDays > 0 && $startTz->gt(Carbon::now($tz)->addDays($maxDays))) {
                    throw ValidationException::withMessages([
                        'booking_date' => "Bookings can only be made up to {$maxDays} days in advance.",
                    ]);
                }
            }

            // Server-side enforcement of the rules the old slot grid pre-filtered:
            // whole-court status + time-ranged blocks (all types) and operating
            // hours + duration bounds (scheduled). The UI is bypassable, so this
            // is the authoritative gate alongside checkAvailability above.
            $this->availability->assertBookable(
                $court,
                $data['booking_date'],
                $data['start_time'],
                $data['end_time'],
                $type
            );

            $durationMinutes = (int) $start->diffInMinutes($end);

            $pricing = $this->pricingService->calculate($court, $start, $end, $durationMinutes);

            $discount = 0;
            if (!empty($data['promo_code'])) {
                $promo = Promotion::where('tenant_id', $court->tenant_id)
                    ->where('code', $data['promo_code'])
                    ->first();

                if ($promo) {
                    $discount = $this->promoEngine
                        ? $this->promoEngine->discountForCourt($promo, $court, $start, $end, $pricing['base_amount'])
                        : ($promo->isValid() ? $promo->calculateDiscount($pricing['base_amount']) : 0);
                }
            }

            // Resolve the requested payment method. Customers must pick one of
            // wallet | court_credit | cash. Legacy callers that only pass
            // use_credit are translated to "court_credit". Anything else
            // defaults to "cash" so the cash-approval workflow kicks in.
            $paymentMethod = $data['payment_method'] ?? null;
            if (!$paymentMethod) {
                $paymentMethod = !empty($data['use_credit']) ? 'court_credit' : 'cash';
            }
            if (!in_array($paymentMethod, Booking::PAYMENT_METHODS, true)) {
                throw ValidationException::withMessages([
                    'payment_method' => 'Invalid payment method.',
                ]);
            }

            // Court credit: only deduct credit minutes if the caller chose this
            // method (or use_credit was passed by a legacy walk-in flow).
            $useCredit = $paymentMethod === 'court_credit';
            $minutesUsed = 0;
            $creditMembership = null;
            if ($useCredit) {
                $membership = $customer->activeMembership;
                if ($membership && $membership->isActive() && $membership->remaining_credits > 0 && $durationMinutes > 0) {
                    $minutesUsed = (int) min($membership->remaining_credits, $durationMinutes);
                    $coverage = $minutesUsed / $durationMinutes;
                    $membership->decrement('remaining_credits', $minutesUsed);
                    $creditMembership = $membership;

                    $creditDiscount         = round($pricing['base_amount'] * $coverage, 2);
                    $pricing['base_amount'] = round($pricing['base_amount'] - $creditDiscount, 2);
                    $pricing['tax']         = round($pricing['tax'] * (1 - $coverage), 2);
                    $pricing['total']       = round($pricing['base_amount'] + $pricing['tax'], 2);
                }
            }

            $totalAmount = max(0, $pricing['total'] - $discount);

            // Wallet always needs full coverage — we auto-debit on create, so
            // there's no "pay the rest at the counter" path. Court credit may
            // partially cover a *walk-in* (uncovered remainder is owed and
            // collected at the desk, matching the legacy behavior); scheduled
            // bookings require full court-credit coverage because they settle
            // on create.
            $createdByStaff = $createdBy && in_array($createdBy->user_type, ['business_owner', 'staff'], true);
            $isCustomerSelfBooking = !$createdByStaff;
            $isWalkIn = ($data['type'] ?? 'online') === 'walk_in';
            if ($paymentMethod === 'wallet' && $customer->wallet_balance < $totalAmount) {
                throw ValidationException::withMessages([
                    'payment_method' => $isCustomerSelfBooking
                        ? 'Insufficient wallet balance. Please top up with venue staff or pick another payment method.'
                        : "Customer's wallet balance (₱" . number_format($customer->wallet_balance, 2)
                          . ") does not cover ₱" . number_format($totalAmount, 2) . '.',
                ]);
            }
            if (!$isWalkIn && $paymentMethod === 'court_credit' && $totalAmount > 0) {
                throw ValidationException::withMessages([
                    'payment_method' => $isCustomerSelfBooking
                        ? 'Your court credit does not fully cover this booking. Please top up or pick another payment method.'
                        : "Customer's court credit does not fully cover this booking.",
                ]);
            }

            // Cash bookings stay pending until owner/staff manually approve —
            // but only when the *customer* creates the booking. When owner or
            // staff create a cash booking from the admin panel, they're the
            // approver already, so the approval prompt is skipped.
            //
            // Venue toggles:
            //   auto_confirm    — skip the manual approval gate and confirm the
            //                     cash booking on create…
            //   require_payment — …unless payment is mandated first, in which
            //                     case it still waits (payment gates confirmation).
            $autoConfirm    = (bool) $court->tenant?->getSetting('auto_confirm', false);
            $requirePayment = (bool) $court->tenant?->getSetting('require_payment', false);

            $needsCashApproval = $paymentMethod === 'cash' && $isCustomerSelfBooking;
            $autoConfirmCash   = $needsCashApproval && $autoConfirm && !$requirePayment;
            $approvalStatus    = ($needsCashApproval && !$autoConfirmCash)
                ? 'pending'
                : 'not_required';

            $booking = Booking::create([
                'tenant_id' => $court->tenant_id,
                'branch_id' => $court->branch_id,
                'court_id' => $court->id,
                'customer_id' => $customer->id,
                'created_by' => $createdBy?->id,
                'type' => $data['type'] ?? 'online',
                'status' => 'pending',
                'booking_date' => $data['booking_date'],
                'start_time' => $data['start_time'],
                'end_time' => $data['end_time'],
                'duration_minutes' => $durationMinutes,
                'base_amount' => $pricing['base_amount'],
                'addon_amount' => 0,
                'discount_amount' => $discount,
                'tax_amount' => $pricing['tax'],
                'total_amount' => $totalAmount,
                'payment_method' => $paymentMethod,
                'approval_status' => $approvalStatus,
                'promo_code' => $data['promo_code'] ?? null,
                'notes' => trim(($data['notes'] ?? '') . ($minutesUsed > 0 ? " [court credit used: {$minutesUsed} min]" : '')) ?: null,
            ]);

            $booking->qr_code = $booking->booking_number;
            $booking->save();

            // Audit ledger for the credit spend (after booking exists so we can reference it).
            if ($minutesUsed > 0 && $creditMembership) {
                MembershipTransaction::create([
                    'membership_id'  => $creditMembership->id,
                    'type'           => 'credit_use',
                    'credits_change' => -$minutesUsed,
                    'description'    => "Used {$minutesUsed} min for booking #{$booking->booking_number}",
                ]);
            }

            // Issue referral reward on customer's *first* completed paying booking.
            $this->issueReferralRewardIfFirstBooking($customer);

            event(new BookingCreated($booking));

            // Auto-settle wallet always (we required full coverage above) and
            // court_credit when nothing remains to collect. Walk-in court_credit
            // with a partial coverage keeps total_amount > 0 — the remainder is
            // collected at the desk, so we don't mark it paid here.
            //
            // Walk-in cash also auto-settles: the customer is at the counter,
            // staff collect the cash on the spot, so we record the Payment row
            // immediately instead of relying on a separate "Collect cash" step.
            //
            // Cash (non-walk-in) bookings: customer-initiated → pending owner/staff approval.
            //                              staff-initiated    → no approval needed (admin flow handles confirm).
            //
            // Online (gateway) bookings: controller redirects to gateway checkout after
            // create(); payment and confirmation happen via the return/webhook path.
            $shouldAutoSettle = $paymentMethod === 'wallet'
                || ($paymentMethod === 'court_credit' && $totalAmount <= 0)
                || ($paymentMethod === 'cash' && $isWalkIn && $totalAmount > 0);
            if ($shouldAutoSettle) {
                $this->settleInstantBooking($booking, $customer, $createdBy);
            } elseif ($autoConfirmCash) {
                // auto_confirm: confirm the unpaid cash booking immediately,
                // skipping staff approval. Cash is still collected at the desk.
                $this->confirm($booking);
            } elseif ($approvalStatus === 'pending') {
                $this->notifyCashPendingApproval($booking);
            }

            return $booking;
        });
    }

    /**
     * Settle a wallet / court_credit / walk-in cash booking: deduct balance
     * (or record the cash), create the audit Payment row where applicable,
     * auto-confirm, and notify everyone.
     *
     * $createdBy is the staff user at the desk and is recorded as the
     * processor of cash collection. It is null only for self-service customer
     * flows, which never reach the cash branch.
     */
    protected function settleInstantBooking(Booking $booking, User $customer, ?User $createdBy = null): void
    {
        $method = $booking->payment_method;
        $amount = (float) $booking->total_amount;

        if ($method === 'wallet' && $amount > 0) {
            $this->walletService->debit(
                $customer,
                $amount,
                "Booking #{$booking->booking_number}",
                $booking,
            );

            // Reporting: every booking settled from a wallet leaves a paid
            // Payment row so revenue/audit reports stay accurate.
            Payment::create([
                'tenant_id'      => $booking->tenant_id,
                'customer_id'    => $customer->id,
                'payable_type'   => Booking::class,
                'payable_id'     => $booking->id,
                'payment_number' => 'PAY-' . strtoupper((string) Str::ulid()),
                'amount'         => $amount,
                'method'         => 'wallet',
                'status'         => 'paid',
                'paid_at'        => now(),
            ]);
        }

        if ($method === 'cash' && $amount > 0) {
            // Walk-in cash is collected on the spot. Recording the Payment
            // here keeps Monthly Revenue accurate without a separate
            // "Collect cash" click.
            Payment::create([
                'tenant_id'      => $booking->tenant_id,
                'customer_id'    => $customer->id,
                'payable_type'   => Booking::class,
                'payable_id'     => $booking->id,
                'payment_number' => 'PAY-' . strtoupper((string) Str::ulid()),
                'amount'         => $amount,
                'method'         => 'cash',
                'status'         => 'paid',
                'paid_at'        => now(),
                'processed_by'   => $createdBy?->id,
                'notes'          => "Walk-in cash collected at desk for booking #{$booking->booking_number}",
            ]);
        }
        // Court credit settlements have no Payment row — the MembershipTransaction
        // ledger entry created in create() is the authoritative audit record.

        $booking->update([
            'paid_amount' => $amount,
            'status'      => 'confirmed',
        ]);

        // Fire the standard confirmed event (which notifies the customer)…
        event(new BookingConfirmed($booking->fresh()));

        // …and additionally notify owner/staff so they see the new prepaid booking
        // (gated by the venue's "New booking created" toggle).
        $this->notifyOwnerStaff(
            $booking->tenant_id,
            new BookingPaidNotification($booking->fresh()),
            'notify_new_booking'
        );
    }

    /**
     * Notify owner/staff that a cash booking needs approval, and the customer
     * that their booking is pending. The standard BookingCreated event has
     * already fired, but it doesn't carry the "approval required" phrasing.
     */
    protected function notifyCashPendingApproval(Booking $booking): void
    {
        $this->notifyOwnerStaff(
            $booking->tenant_id,
            new BookingApprovalRequiredNotification($booking)
        );
        $booking->customer?->notify(new BookingPendingApprovalNotification($booking));
    }

    /**
     * Send the same notification to every active business_owner + staff in the
     * tenant. When $toggleKey is given, the venue's settings.notifications
     * toggle gates delivery (defaults ON). If the venue set a notification_email,
     * a copy is also sent there (best-effort — never breaks the booking flow).
     */
    protected function notifyOwnerStaff(int $tenantId, $notification, ?string $toggleKey = null): void
    {
        $tenant = Tenant::find($tenantId);

        if ($toggleKey && $tenant && !$tenant->wantsNotification($toggleKey)) {
            return;
        }

        if ($email = $tenant?->notificationEmail()) {
            try {
                // AnonymousNotifiable has no notifications() relationship, so channelsForUser()
                // returning 'database'/'webpush' would throw and swallow the email. Force mail only.
                Notification::route('mail', $email)->notify(new class($notification) extends \Illuminate\Notifications\Notification {
                    use \Illuminate\Bus\Queueable;
                    public function __construct(private readonly \Illuminate\Notifications\Notification $inner) {}
                    public function via(object $n): array { return ['mail']; }
                    public function toMail(object $n): mixed { return $this->inner->toMail($n); }
                });
            } catch (\Throwable $e) {
                Log::warning('notification_email CC failed', ['email' => $email, 'error' => $e->getMessage()]);
            }
        }
    }

    /**
     * Owner/staff approves a pending cash booking. The slot is finalised and
     * the customer is notified.
     */
    public function approveCashBooking(Booking $booking, User $approver): Booking
    {
        return DB::transaction(function () use ($booking, $approver) {
            if ($booking->approval_status !== 'pending') {
                throw ValidationException::withMessages([
                    'booking' => 'This booking is not awaiting approval.',
                ]);
            }
            if ($booking->payment_method !== 'cash') {
                throw ValidationException::withMessages([
                    'booking' => 'Only cash bookings require manual approval.',
                ]);
            }

            $booking->update([
                'approval_status' => 'approved',
                'approved_by'     => $approver->id,
                'approved_at'     => now(),
                'status'          => 'confirmed',
            ]);

            event(new BookingConfirmed($booking->fresh()));
            $booking->customer?->notify(new BookingApprovedNotification($booking->fresh()));

            return $booking->fresh();
        });
    }

    /**
     * Owner/staff denies a pending cash booking. A denial note is required and
     * forwarded to the customer.
     */
    public function denyCashBooking(Booking $booking, User $denier, string $note): Booking
    {
        return DB::transaction(function () use ($booking, $denier, $note) {
            if ($booking->approval_status !== 'pending') {
                throw ValidationException::withMessages([
                    'booking' => 'This booking is not awaiting approval.',
                ]);
            }
            $note = trim($note);
            if ($note === '') {
                throw ValidationException::withMessages([
                    'denial_note' => 'A denial note is required.',
                ]);
            }

            $booking->update([
                'approval_status'     => 'denied',
                'denied_by'           => $denier->id,
                'denied_at'           => now(),
                'denial_note'         => $note,
                'status'              => 'denied',
                'cancellation_reason' => 'Denied: ' . $note,
            ]);

            $booking->customer?->notify(new BookingDeniedNotification($booking->fresh(), $note));

            return $booking->fresh();
        });
    }

    public function confirm(Booking $booking): Booking
    {
        if ($booking->status === 'confirmed') {
            return $booking;
        }
        $booking->update(['status' => 'confirmed']);
        event(new BookingConfirmed($booking));
        return $booking;
    }

    /**
     * Whether the venue's require_payment policy blocks confirming this booking
     * because it still owes money. Used by admin flows to keep unpaid bookings
     * pending until cash is collected. Returns false when the toggle is off.
     */
    public function paymentRequiredAndUnpaid(Booking $booking): bool
    {
        $requirePayment = (bool) $booking->tenant?->getSetting('require_payment', false);

        return $requirePayment
            && (float) $booking->total_amount > 0
            && (float) $booking->paid_amount < (float) $booking->total_amount;
    }

    /**
     * Enforce the venue's minimum cancellation lead time for *customer* self-
     * cancellations. Disabled when the setting is unset/0 (behaviour unchanged
     * until a venue configures it). Throws so it surfaces the same way on web
     * (redirect-back with errors) and API (422) — both callers go through here
     * so the policy can't be bypassed by hitting the API directly (H-1).
     */
    public function assertCancellable(Booking $booking): void
    {
        $cancellationHours = (int) ($booking->tenant?->getSetting('cancellation_hours', 0));
        if ($cancellationHours <= 0) {
            return;
        }

        $tz    = $booking->tenant?->timezone ?: config('app.timezone');
        $start = Carbon::parse(
            $booking->booking_date->format('Y-m-d') . ' ' . Carbon::parse($booking->start_time)->format('H:i'),
            $tz
        );

        if (Carbon::now($tz)->diffInHours($start, false) < $cancellationHours) {
            throw ValidationException::withMessages([
                'booking' => "Bookings must be cancelled at least {$cancellationHours} hours before the start time. Please contact the venue.",
            ]);
        }
    }

    /**
     * Customer-initiated cancellation: enforce the lead-time window, then cancel
     * with the venue's refund policy applied (refund = true, bypassWindow = false).
     * Shared by the web + API customer flows so they behave identically.
     */
    public function customerCancel(Booking $booking, string $reason): Booking
    {
        $this->assertCancellable($booking);

        return $this->cancel($booking, $reason, refund: true);
    }

    /**
     * @param  bool  $bypassWindow  Admin override — when true, refund the full
     *                              paid_amount regardless of the venue's refund-
     *                              window policy. Customer self-cancels always
     *                              pass false (policy applies).
     */
    public function cancel(Booking $booking, string $reason, bool $refund = false, bool $bypassWindow = false): Booking
    {
        return DB::transaction(function () use ($booking, $reason, $refund, $bypassWindow) {
            // Idempotency guard (FIN-02): a booking that is already cancelled/denied
            // must never be cancelled — and refunded — a second time. Without this,
            // repeated cancel calls re-credit the wallet / re-restore court-credit
            // minutes on every invocation, minting balance out of thin air.
            if (in_array($booking->status, ['cancelled', 'denied'], true)) {
                return $booking;
            }

            $booking->update([
                'status' => 'cancelled',
                'cancelled_at' => now(),
                'cancellation_reason' => $reason,
            ]);

            // If a timer was running (walk-in or active session), stop it so the
            // court's status board doesn't keep ticking it down.
            $booking->timer()
                ->whereIn('status', ['running', 'paused', 'overtime'])
                ->update([
                    'status'     => 'stopped',
                    'stopped_at' => now(),
                ]);

            if ($refund) {
                $this->issueRefund($booking, $bypassWindow);
            }

            // Reset court status synchronously so the board never shows
            // "Occupied" after cancellation, even if the queue worker is down.
            $court = $booking->court;
            if (in_array($court->status, ['occupied', 'reserved'], true)) {
                $hasOther = $court->bookings()
                    ->where('id', '!=', $booking->id)
                    ->whereIn('status', ['active', 'confirmed'])
                    ->where('booking_date', today())
                    ->exists();
                if (!$hasOther) {
                    $court->update(['status' => 'available']);
                }
            }

            event(new BookingCancelled($booking));

            // Owner/staff alert (gated by the venue's "Booking cancelled" toggle).
            // Dispatched AFTER commit so a mail failure can never roll back the
            // cancellation (the live 530-auth 500). notifyOwnerStaff also guards
            // each send in try/catch as a second layer of defence.
            $fresh = $booking->fresh();
            DB::afterCommit(function () use ($fresh) {
                $this->notifyOwnerStaff(
                    $fresh->tenant_id,
                    new BookingCancelledNotification($fresh),
                    'notify_cancellation'
                );
            });

            return $booking;
        });
    }

    /**
     * Lead-time based refund policy:
     *   ≥ full window: 100%
     *   ≥ partial window: partial %
     *   else: 0
     */
    public function computeRefundAmount(Booking $booking): float
    {
        $tenant = $booking->tenant;
        $settings = $tenant?->settings ?? null;
        $fullWindow     = (int)   data_get($settings, 'refund.full_window_hours', 24);
        $partialWindow  = (int)   data_get($settings, 'refund.partial_window_hours', 6);
        $partialPercent = (float) data_get($settings, 'refund.partial_percent', 50);

        // Booking TIME columns are tenant wall-clock, not UTC. Anchor both the
        // start and "now" to the tenant timezone, otherwise the lead-time lands
        // in the wrong refund-window band (off by the tz offset) — e.g. a Manila
        // venue on a UTC app clock would over-credit by ~8h of lead time.
        $tz = $tenant?->timezone ?: config('app.timezone');
        $start = Carbon::parse(
            $booking->booking_date->format('Y-m-d') . ' ' . Carbon::parse($booking->start_time)->format('H:i'),
            $tz
        );
        $hoursAhead = Carbon::now($tz)->diffInMinutes($start, false) / 60;

        if ($hoursAhead >= $fullWindow)    return (float) $booking->paid_amount;
        if ($hoursAhead >= $partialWindow) return round($booking->paid_amount * $partialPercent / 100, 2);
        return 0.0;
    }

    /**
     * Deliver a refund in the same form the booking was paid:
     *   wallet       → credit wallet immediately + mark Payment(s) refunded
     *   court_credit → restore the exact minutes that were spent
     *   cash         → open a pending RefundRequest for staff to settle at the
     *                  desk; the Payment is only marked refunded once staff
     *                  process the request (money actually leaves the till).
     *
     * Each branch is no-op-safe if there is nothing to refund.
     *
     * $bypassWindow lets admin cancellations refund the full paid_amount
     * regardless of the venue's refund-window policy. Customer self-cancels
     * always pass false (policy applies).
     */
    protected function issueRefund(Booking $booking, bool $bypassWindow = false): void
    {
        $method = $booking->payment_method;

        if ($method === 'court_credit') {
            $this->refundCourtCreditMinutes($booking);
            return;
        }

        // For wallet/cash/online refunds, the policy gives a peso amount unless
        // an admin override is in play (in which case refund the whole thing).
        if ($booking->paid_amount <= 0) {
            return;
        }
        $amount = $bypassWindow
            ? (float) $booking->paid_amount
            : $this->computeRefundAmount($booking);
        if ($amount <= 0) {
            return;
        }

        // Online (gateway) payments refund to the customer's wallet — we don't
        // trigger a reverse charge through the gateway, so the money lands
        // immediately and the customer can use it for their next booking.
        if ($method === 'online') {
            $this->walletService->credit(
                $booking->customer,
                $amount,
                'Refund for booking #' . $booking->booking_number,
                $booking
            );
            $this->markPaymentsRefunded($booking, $amount);
            return;
        }

        if ($method === 'wallet') {
            $this->walletService->credit(
                $booking->customer,
                $amount,
                'Refund for booking #' . $booking->booking_number,
                $booking
            );
            // Wallet refunds are immediate — money actually moved, so the
            // original Payment row(s) must reflect that for revenue accuracy.
            $this->markPaymentsRefunded($booking, $amount);
            return;
        }

        if ($method === 'cash') {
            // Don't create a duplicate request if cancel() somehow runs twice.
            $existing = RefundRequest::where('booking_id', $booking->id)
                ->where('status', 'pending')
                ->exists();
            if ($existing) {
                return;
            }

            RefundRequest::create([
                'tenant_id'   => $booking->tenant_id,
                'booking_id'  => $booking->id,
                'customer_id' => $booking->customer_id,
                'method'      => 'cash',
                'amount'      => $amount,
                'status'      => 'pending',
                'reason'      => $booking->cancellation_reason,
            ]);
            // Note: Payment row is NOT marked refunded yet — staff still has
            // to pay out at the desk. processCashRefund() marks it then.
        }
    }

    /**
     * Forget the cached Monthly Revenue / Today / dashboard tiles for this
     * tenant so a freshly-processed refund or cash collection is visible
     * immediately instead of after the 60–300 s TTL.
     *
     * Database cache driver doesn't support tags, so we explicitly forget the
     * exact keys built by DashboardController + ReportService::revenueSummary.
     */
    protected function invalidateRevenueCache(int $tenantId): void
    {
        $today      = now()->toDateString();
        $monthStart = now()->startOfMonth()->toDateString();
        $monthEnd   = now()->endOfMonth()->toDateString();

        $branchIds = Branch::where('tenant_id', $tenantId)->pluck('id')->push('all');

        foreach ($branchIds as $bid) {
            Cache::forget("dashboard.{$tenantId}.{$bid}");
            Cache::forget("report.revenue.{$tenantId}.{$monthStart}.{$monthEnd}.{$bid}");
            Cache::forget("report.revenue.{$tenantId}.{$today}.{$today}.{$bid}");
        }
    }

    /**
     * Allocate a refund peso amount across this booking's paid Payment rows,
     * oldest first. Each row's refund_amount/refunded_at is updated, and the
     * status flips to 'refunded' once a row is fully covered.
     *
     * Revenue reports subtract refund_amount from amount so the Monthly
     * Revenue tile correctly drops by the refunded amount.
     */
    protected function markPaymentsRefunded(Booking $booking, float $amount): void
    {
        $remaining = round($amount, 2);
        if ($remaining <= 0) return;

        $payments = $booking->payments()
            ->whereIn('status', ['paid', 'refunded'])
            ->orderBy('paid_at')
            ->get();

        foreach ($payments as $p) {
            if ($remaining <= 0) break;

            $alreadyRefunded = (float) $p->refund_amount;
            $refundable      = max(0, (float) $p->amount - $alreadyRefunded);
            if ($refundable <= 0) continue;

            $apply = round(min($refundable, $remaining), 2);
            $p->update([
                'refund_amount' => round($alreadyRefunded + $apply, 2),
                'refunded_at'   => now(),
                'status'        => ($apply >= $refundable) ? 'refunded' : 'paid',
            ]);
            $remaining = round($remaining - $apply, 2);
        }

        // Refund changed monthly/today totals — drop the cached values so
        // the next dashboard load shows the new numbers.
        $this->invalidateRevenueCache($booking->tenant_id);
    }

    /**
     * Court-credit refund: re-credit the exact minutes that were spent on this
     * booking. The original spend was logged as a `credit_use` MembershipTransaction
     * tagged with the booking number, so we reverse that one row.
     */
    protected function refundCourtCreditMinutes(Booking $booking): void
    {
        $spend = MembershipTransaction::where('type', 'credit_use')
            ->where('credits_change', '<', 0)
            ->where('description', 'like', '%booking #' . $booking->booking_number . '%')
            ->latest()
            ->first();

        if (!$spend) {
            return;
        }

        $minutes = (int) abs($spend->credits_change);
        if ($minutes <= 0) {
            return;
        }

        $membership = $spend->membership;
        if (!$membership) {
            return;
        }

        $membership->increment('remaining_credits', $minutes);

        MembershipTransaction::create([
            'membership_id'  => $membership->id,
            'type'           => 'refund',
            'credits_change' => $minutes,
            'description'    => "Refund {$minutes} min for cancelled booking #{$booking->booking_number}",
        ]);
    }

    /**
     * Mark a pending cash RefundRequest as paid out at the desk. Returns the
     * fresh request, or throws if it's not in a payable state.
     */
    public function processCashRefund(RefundRequest $request, User $staff, ?string $reference = null): RefundRequest
    {
        if ($request->status !== 'pending') {
            throw ValidationException::withMessages(['refund' => 'This refund is not pending.']);
        }
        if ($request->method !== 'cash') {
            throw ValidationException::withMessages(['refund' => 'Only cash refunds are settled here.']);
        }

        return DB::transaction(function () use ($request, $staff, $reference) {
            $request->update([
                'status'       => 'processed',
                'reference'    => $reference,
                'processed_by' => $staff->id,
                'processed_at' => now(),
            ]);

            // Cash has now left the till — mark the original booking Payment(s)
            // refunded so Monthly Revenue drops accordingly.
            if ($request->booking) {
                $this->markPaymentsRefunded($request->booking, (float) $request->amount);
            }

            return $request->fresh();
        });
    }

    /**
     * Record a cash payment collected at the desk against a cash booking.
     * Creates a paid Payment row, increments paid_amount, and (if fully
     * settled) leaves the booking with no remaining balance.
     *
     * Partial collection is supported — multiple calls accumulate.
     */
    public function recordCashPayment(Booking $booking, User $staff, float $amount, ?string $reference = null): Payment
    {
        if ($booking->payment_method !== 'cash') {
            throw ValidationException::withMessages([
                'payment_method' => 'Only cash bookings can be collected here.',
            ]);
        }
        if (in_array($booking->status, ['cancelled', 'denied'], true)) {
            throw ValidationException::withMessages([
                'booking' => 'This booking cannot be collected — it is ' . $booking->status . '.',
            ]);
        }

        $amount = round((float) $amount, 2);
        if ($amount <= 0) {
            throw ValidationException::withMessages(['amount' => 'Amount must be greater than zero.']);
        }

        $remaining = round((float) $booking->total_amount - (float) $booking->paid_amount, 2);
        if ($amount > $remaining) {
            throw ValidationException::withMessages([
                'amount' => 'Amount exceeds the remaining balance of ₱' . number_format($remaining, 2) . '.',
            ]);
        }

        return DB::transaction(function () use ($booking, $staff, $amount, $reference) {
            $payment = Payment::create([
                'tenant_id'       => $booking->tenant_id,
                'customer_id'     => $booking->customer_id,
                'payable_type'    => Booking::class,
                'payable_id'      => $booking->id,
                'payment_number'  => 'PAY-' . strtoupper((string) Str::ulid()),
                'amount'          => $amount,
                'method'          => 'cash',
                'status'          => 'paid',
                'paid_at'         => now(),
                'processed_by'    => $staff->id,
                'receipt_number'  => $reference,
                'notes'           => "Cash collected at desk for booking #{$booking->booking_number}",
            ]);

            $booking->increment('paid_amount', $amount);

            $this->invalidateRevenueCache($booking->tenant_id);

            return $payment;
        });
    }

    public function denyCashRefund(RefundRequest $request, User $staff, string $note): RefundRequest
    {
        if ($request->status !== 'pending') {
            throw ValidationException::withMessages(['refund' => 'This refund is not pending.']);
        }
        $note = trim($note);
        if ($note === '') {
            throw ValidationException::withMessages(['denial_note' => 'A denial note is required.']);
        }

        $request->update([
            'status'       => 'denied',
            'denial_note'  => $note,
            'processed_by' => $staff->id,
            'processed_at' => now(),
        ]);

        return $request->fresh();
    }

    public function reschedule(Booking $booking, string $newDate, string $newStart, string $newEnd): Booking
    {
        return DB::transaction(function () use ($booking, $newDate, $newStart, $newEnd) {
            if (in_array($booking->status, ['cancelled', 'completed', 'active'])) {
                throw ValidationException::withMessages(['booking' => 'This booking cannot be rescheduled.']);
            }

            // Concurrency guard (BOOK-01/BOOK-02): serialize on the court row so the
            // availability check below can't race a concurrent create/reschedule.
            $court = Court::whereKey($booking->court_id)->lockForUpdate()->firstOrFail();

            if (!$this->checkAvailability($booking->court_id, $newDate, $newStart, $newEnd, $booking->id)) {
                throw ValidationException::withMessages(['time_slot' => 'New slot is unavailable.']);
            }

            // Re-apply the same create() guards (H-3): whole-court status, operating
            // hours and duration bounds. Without this a booking could be moved onto
            // a maintenance/closed court or outside operating hours.
            $this->availability->assertBookable($court, $newDate, $newStart, $newEnd, $booking->type);

            // The new slot must be strictly in the future, in the tenant's
            // wall-clock — TIME columns are wall-clock, not UTC.
            $tz      = $court->tenant?->timezone ?: config('app.timezone');
            $startTz = Carbon::parse($newDate . ' ' . $newStart, $tz);
            if ($startTz->lte(Carbon::now($tz))) {
                throw ValidationException::withMessages([
                    'start_time' => 'That time slot has already started. Please pick a future time.',
                ]);
            }

            $start = Carbon::parse($newDate . ' ' . $newStart, $tz);
            $end   = Carbon::parse($newDate . ' ' . $newEnd, $tz);
            $duration = (int) $start->diffInMinutes($end);

            // Re-price for the new slot (H-2). The previous behaviour kept the
            // original amount, so moving an off-peak booking into a peak/weekend
            // window (or changing duration) silently mispriced it. Existing
            // discount + add-on lines are preserved; the total is recomputed and
            // paid_amount is left untouched so any difference shows as an
            // outstanding balance (or an overpayment) for staff to settle.
            $pricing = $this->pricingService->calculate($court, $start, $end, $duration);
            $discount = (float) $booking->discount_amount;
            $addon    = (float) $booking->addon_amount;
            $total    = max(0, round($pricing['base_amount'] + $pricing['tax'] + $addon - $discount, 2));

            $booking->update([
                'booking_date'     => $newDate,
                'start_time'       => $newStart,
                'end_time'         => $newEnd,
                'duration_minutes' => $duration,
                'base_amount'      => $pricing['base_amount'],
                'tax_amount'       => $pricing['tax'],
                'total_amount'     => $total,
            ]);

            return $booking->fresh();
        });
    }

    /**
     * Preview a walk-in: returns proposed times + any subsequent bookings on
     * the same court that the requested duration would overlap.
     *
     *   [
     *     'start'           => '20:37',          // H:i (now)
     *     'requested_end'   => '21:37',          // H:i (now + duration)
     *     'duration_min'    => 60,
     *     'capped_end'      => '21:00',          // H:i — end if you cap to fit
     *     'capped_minutes'  => 23,               // mins available before next conflict
     *     'conflicts'       => [                  // bookings that would be bumped if not capped
     *       [
     *         'booking_id'      => 5,
     *         'booking_number'  => 'BK-2026-0005',
     *         'customer'        => 'Juan Dela Cruz',
     *         'old_start'       => '21:00',
     *         'old_end'         => '22:00',
     *         'new_start'       => '21:37',
     *         'new_end'         => '22:37',
     *       ],
     *       ...
     *     ],
     *   ]
     */
    public function previewWalkIn(Court $court, int $durationMinutes): array
    {
        $tz           = $court->tenant?->timezone ?: config('app.timezone');
        $now          = now($tz);
        $today        = $now->toDateString();
        $start        = $now->copy();
        $requestedEnd = $start->copy()->addMinutes($durationMinutes);

        $nowStr = $start->format('H:i:s');
        $endStr = $requestedEnd->format('H:i:s');

        // Operating hours check — walk-ins must also fall within the branch window.
        [$open, $close, $isOpenDay] = $this->availability->operatingWindowPublic($court, $today, $tz);
        $toMin = fn(string $hm) => (int) explode(':', $hm)[0] * 60 + (int) explode(':', $hm)[1];
        $crossesMidnight = $requestedEnd->format('H:i:s') < $start->format('H:i:s');
        $outsideHours = $toMin($start->format('H:i')) < $toMin($open)
            || $toMin($start->format('H:i')) >= $toMin($close)
            || $toMin($requestedEnd->format('H:i')) > $toMin($close)
            || $crossesMidnight;
        if (! $isOpenDay || $outsideHours) {
            $fmt = fn(string $hm) => \Carbon\Carbon::createFromFormat('H:i', substr($hm, 0, 5))->format('g:i A');
            $message = ! $isOpenDay
                ? 'The venue is closed today.'
                : 'Selected time is outside operating hours (' . $fmt($open) . ' – ' . $fmt($close) . ').';
            return [
                'start'           => $start->format('H:i'),
                'requested_end'   => $requestedEnd->format('H:i'),
                'duration_min'    => $durationMinutes,
                'capped_end'      => $start->format('H:i'),
                'capped_minutes'  => 0,
                'conflicts'       => [],
                'current_booking' => null,
                'outside_hours'   => $message,
            ];
        }

        // All bookings on this court today whose [start, end] overlaps [now, requestedEnd].
        // Includes currently-active bookings (start ≤ now) so we don't silently miss
        // a session already in progress.
        $overlapping = Booking::where('court_id', $court->id)
            ->where('booking_date', $today)
            ->whereIn('status', ['pending', 'confirmed', 'active'])
            ->where('start_time', '<', $endStr)
            ->where('end_time',   '>', $nowStr)
            ->orderBy('start_time')
            ->with('customer:id,name')
            ->get();

        // Separate "currently in progress" from "subsequent". A current booking
        // can't be capped (no time available) nor bumped (already running) —
        // staff must wait for it to end.
        $current = $overlapping->first(function ($b) use ($nowStr) {
            return Carbon::parse($b->start_time)->format('H:i:s') <= $nowStr;
        });

        if ($current) {
            $currentEnd = Carbon::parse(
                $today . ' ' . Carbon::parse($current->end_time)->format('H:i:s'),
                $tz
            );
            return [
                'start'           => $start->format('H:i'),
                'requested_end'   => $requestedEnd->format('H:i'),
                'duration_min'    => $durationMinutes,
                'capped_end'      => $start->format('H:i'),
                'capped_minutes'  => 0,
                'conflicts'       => [],
                'current_booking' => [
                    'booking_id'     => $current->id,
                    'booking_number' => $current->booking_number,
                    'customer'       => $current->customer?->name ?? 'Walk-in',
                    'start'          => Carbon::parse($current->start_time)->format('H:i'),
                    'end'            => Carbon::parse($current->end_time)->format('H:i'),
                    'message'        => 'Court is currently in use until ' . $currentEnd->format('g:i A')
                                      . '. End that session first, or pick a different court.',
                ],
            ];
        }

        // No current booking — apply chain-shift to the subsequent ones that overlap.
        $cappedEnd = $requestedEnd->copy();
        $cappedMin = $durationMinutes;
        $conflicts = [];

        if ($overlapping->isNotEmpty()) {
            $first = $overlapping->first();
            $firstStart = Carbon::parse($today . ' ' . Carbon::parse($first->start_time)->format('H:i:s'), $tz);

            if ($firstStart->lt($requestedEnd)) {
                $cappedEnd = $firstStart->copy();
                $cappedMin = (int) $start->diffInMinutes($cappedEnd);

                // Chain-shift: each subsequent booking gets pushed by the overlap amount
                // computed against the *previous* (already-shifted) booking, so chains
                // resolve naturally without secondary overlaps.
                $cursor = $requestedEnd->copy(); // walk-in's end is the new wall
                foreach ($overlapping as $b) {
                    $bStart = Carbon::parse($today . ' ' . Carbon::parse($b->start_time)->format('H:i:s'), $tz);
                    $bEnd   = Carbon::parse($today . ' ' . Carbon::parse($b->end_time)->format('H:i:s'),   $tz);

                    if ($bStart->gte($cursor)) {
                        break;
                    }

                    $shift    = (int) $bStart->diffInMinutes($cursor);
                    $newStart = $bStart->copy()->addMinutes($shift);
                    $newEnd   = $bEnd->copy()->addMinutes($shift);

                    $conflicts[] = [
                        'booking_id'     => $b->id,
                        'booking_number' => $b->booking_number,
                        'customer'       => $b->customer?->name ?? 'Walk-in',
                        'old_start'      => $bStart->format('H:i'),
                        'old_end'        => $bEnd->format('H:i'),
                        'new_start'      => $newStart->format('H:i'),
                        'new_end'        => $newEnd->format('H:i'),
                    ];

                    $cursor = $newEnd->copy();
                }
            }
        }

        return [
            'start'           => $start->format('H:i'),
            'requested_end'   => $requestedEnd->format('H:i'),
            'duration_min'    => $durationMinutes,
            'capped_end'      => $cappedEnd->format('H:i'),
            'capped_minutes'  => $cappedMin,
            'conflicts'       => $conflicts,
            'current_booking' => null,
        ];
    }

    /**
     * Walk-in fast-path: create + confirm + start timer.
     *
     * $mode controls conflict handling:
     *   'auto' — no conflict check (legacy behavior)
     *   'cap'  — shorten walk-in to fit before the next booking
     *   'bump' — keep full duration; shift subsequent overlapping bookings forward
     */
    public function walkIn(array $data, ?User $customer, User $createdBy, string $mode = 'auto'): Booking
    {
        return DB::transaction(function () use ($data, $customer, $createdBy, $mode) {
            $data['type']         = 'walk_in';
            $data['booking_date'] = $data['booking_date'] ?? today()->toDateString();

            // Concurrency guard (BOOK-01): lock the court for the walk-in's
            // conflict preview + bump so two desks can't grab the same slot.
            $court = Court::whereKey($data['court_id'])->lockForUpdate()->firstOrFail();
            $tz    = $court->tenant?->timezone ?: config('app.timezone');
            $now   = now($tz);

            $duration = (int) ($data['duration_minutes'] ?? 60);
            $start    = $now->copy();
            $end      = $start->copy()->addMinutes($duration);

            // Conflict handling
            if (in_array($mode, ['cap', 'bump'], true)) {
                $preview = $this->previewWalkIn($court, $duration);

                if ($mode === 'cap' && $preview['capped_minutes'] < $duration) {
                    $duration = $preview['capped_minutes'];
                    $end      = $start->copy()->addMinutes($duration);
                    if ($duration <= 0) {
                        throw ValidationException::withMessages([
                            'duration_minutes' => 'No time available before the next booking on this court.',
                        ]);
                    }
                }

                if ($mode === 'bump' && !empty($preview['conflicts'])) {
                    foreach ($preview['conflicts'] as $c) {
                        $shifted = Booking::find($c['booking_id']);
                        if (!$shifted) {
                            continue;
                        }

                        // Don't silently push a booking past closing time / onto a
                        // blocked court (M-2). assertBookable validates the shifted
                        // window's operating hours + court status + duration; a
                        // failure throws and rolls back the whole walk-in so staff
                        // can choose 'cap' instead.
                        $shiftDate = $shifted->booking_date instanceof Carbon
                            ? $shifted->booking_date->format('Y-m-d')
                            : (string) $shifted->booking_date;
                        $this->availability->assertBookable(
                            $court, $shiftDate, $c['new_start'], $c['new_end'], $shifted->type
                        );

                        $oldStart = Carbon::parse($shifted->start_time)->format('H:i');
                        $oldEnd   = Carbon::parse($shifted->end_time)->format('H:i');

                        // Model update (not a mass ->update()) so the change is
                        // captured by the activity log + model events (M-2).
                        $shifted->update([
                            'start_time' => $c['new_start'],
                            'end_time'   => $c['new_end'],
                        ]);

                        // Tell the bumped customer their time moved. Best-effort —
                        // a failed notification must never break the walk-in.
                        try {
                            $shifted->customer?->notify(new BookingRescheduledNotification(
                                $shifted->fresh(), $oldStart, $oldEnd, $c['new_start'], $c['new_end']
                            ));
                        } catch (\Throwable $e) {
                            Log::warning('Bump reschedule notification failed', [
                                'booking_id' => $shifted->id,
                                'error'      => $e->getMessage(),
                            ]);
                        }
                    }
                }
            }

            $data['start_time'] = $start->format('H:i');
            $data['end_time']   = $end->format('H:i');

            $customer = $customer ?? $this->getOrCreateWalkInGuest($createdBy);

            $booking = $this->create($data, $customer, $createdBy);
            $this->confirm($booking);
            $this->startTimer($booking);

            return $booking->fresh(['court', 'timer', 'customer']);
        });
    }

    /**
     * If this customer was referred and this is their first booking, credit the referrer.
     * Idempotent: only the *first* booking triggers it. Reward amount is from
     * tenant settings `referral.reward_amount` (default 100).
     */
    protected function issueReferralRewardIfFirstBooking(User $customer): void
    {
        if (!$customer->referred_by) return;
        // Count = 1 because the booking we just created is already in this transaction.
        $bookingCount = Booking::where('customer_id', $customer->id)->count();
        if ($bookingCount > 1) return;

        $referrer = User::find($customer->referred_by);
        if (!$referrer || $referrer->tenant_id !== $customer->tenant_id) return;

        $reward = (float) data_get($customer->tenant?->settings, 'referral.reward_amount', 100);
        if ($reward <= 0) return;

        $this->walletService->credit(
            $referrer,
            $reward,
            "Referral reward — {$customer->name} signed up & booked",
            $customer
        );
    }

    private function getOrCreateWalkInGuest(User $createdBy): User
    {
        return User::firstOrCreate(
            ['tenant_id' => $createdBy->tenant_id, 'email' => 'walkin@tenant' . $createdBy->tenant_id . '.local'],
            [
                'name'      => 'Walk-in Guest',
                'password'  => bcrypt(str()->random(32)),
                'user_type' => 'customer',
                'is_active' => true,
            ]
        );
    }

    public function startTimer(Booking $booking): BookingTimer
    {
        return DB::transaction(function () use ($booking) {
            $court = $booking->court;
            $tz    = $booking->tenant?->timezone ?: config('app.timezone');

            $dateStr = $booking->booking_date instanceof \Carbon\Carbon
                ? $booking->booking_date->format('Y-m-d')
                : (string) $booking->booking_date;

            $bookedStart = Carbon::parse(
                $dateStr . ' ' . Carbon::parse($booking->start_time)->format('H:i'),
                $tz
            );
            $bookedEnd = Carbon::parse(
                $dateStr . ' ' . Carbon::parse($booking->end_time)->format('H:i'),
                $tz
            );

            $durationMinutes = (int) ($booking->duration_minutes ?: $bookedStart->diffInMinutes($bookedEnd));
            $actualStart     = now($tz);

            // Early-start shift: if the previous session ended early and we're
            // starting before the booked time, shift the whole window earlier
            // so the duration stays exactly what was booked. Late starts keep
            // the original end so the next booking on this court isn't delayed.
            if ($booking->type === 'walk_in' || $actualStart->lt($bookedStart)) {
                // Walk-ins start "now"; early scheduled starts shift the window
                // earlier. Either way, anchor the end to the ACTUAL start instant +
                // the booked duration so the player always gets the full time.
                // Without this, a walk-in begun mid-minute (e.g. 18:21:31) inherits
                // the H:i-truncated booked end (18:51:00) and silently loses the
                // sub-minute remainder — the "30 min" session reads 29:29 at start.
                $scheduledEnd     = $actualStart->copy()->addMinutes($durationMinutes);
                $newStartTime     = $actualStart->format('H:i');
                $newEndTime       = $scheduledEnd->format('H:i');
            } else {
                // On-time / late scheduled start: keep the original booked end so the
                // next reservation on this court isn't pushed back.
                $scheduledEnd     = $bookedEnd;
                $newStartTime     = null;   // keep original
                $newEndTime       = null;
            }

            $graceMinutes = (int) ($booking->tenant?->settings['grace_period_minutes'] ?? 5);

            $timer = BookingTimer::create([
                'booking_id'           => $booking->id,
                'court_id'             => $court->id,
                'status'               => 'running',
                'started_at'           => $actualStart,
                'scheduled_end_at'     => $scheduledEnd,
                'grace_period_seconds' => $graceMinutes * 60,
                'overtime_rate'        => $court->base_hourly_rate,
            ]);

            $update = ['status' => 'active', 'checked_in_at' => $actualStart];
            if ($newStartTime !== null) {
                $update['start_time'] = $newStartTime;
                $update['end_time']   = $newEndTime;
                // duration_minutes is unchanged on purpose.
            }
            $booking->update($update);
            $court->update(['status' => 'occupied']);

            return $timer;
        });
    }

    public function pauseTimer(BookingTimer $timer): BookingTimer
    {
        $timer->update(['status' => 'paused', 'paused_at' => now()]);
        return $timer;
    }

    public function resumeTimer(BookingTimer $timer): BookingTimer
    {
        // Carbon 3 diffInSeconds returns a signed float; take absolute and cast to int.
        $pausedSeconds = $timer->paused_at
            ? (int) abs($timer->paused_at->diffInSeconds(now()))
            : 0;
        $timer->update([
            'status' => 'running',
            'resumed_at' => now(),
            'paused_seconds' => $timer->paused_seconds + $pausedSeconds,
            'paused_at' => null,
        ]);
        return $timer;
    }

    public function extendTimer(BookingTimer $timer, int $minutes): BookingTimer
    {
        return DB::transaction(function () use ($timer, $minutes) {
            $booking = $timer->booking;

            // Conflict guard (H-3): an extension that runs into the next booking
            // on this court would silently double-commit the court. Serialize on
            // the court row, then reject if the extended window overlaps another
            // live booking. Staff get a clear "next booking starts at …" error.
            $court = Court::whereKey($timer->court_id)->lockForUpdate()->firstOrFail();

            $currentEnd = $timer->scheduled_end_at->copy();
            $newEnd     = $currentEnd->copy()->addMinutes($minutes);

            $dateStr = $booking?->booking_date instanceof Carbon
                ? $booking->booking_date->format('Y-m-d')
                : (string) $booking?->booking_date;

            if ($booking && $dateStr) {
                $conflict = Booking::where('court_id', $court->id)
                    ->where('id', '!=', $booking->id)
                    ->where('booking_date', $dateStr)
                    ->whereIn('status', ['pending', 'confirmed', 'active'])
                    ->where('start_time', '<', $newEnd->format('H:i:s'))
                    ->where('end_time',   '>', $currentEnd->format('H:i:s'))
                    ->orderBy('start_time')
                    ->first(['id', 'start_time', 'booking_number']);

                if ($conflict) {
                    throw ValidationException::withMessages([
                        'minutes' => 'Cannot extend by ' . $minutes . ' min — the next booking on this court ('
                            . $conflict->booking_number . ') starts at '
                            . Carbon::parse($conflict->start_time)->format('g:i A') . '.',
                    ]);
                }
            }

            // copy() prevents in-place mutation of the cached Carbon; otherwise Eloquent's
            // dirty check compares the (mutated) new value against the (also mutated) original
            // and skips the write.
            $timer->update([
                'scheduled_end_at'  => $timer->scheduled_end_at->copy()->addMinutes($minutes),
                'extension_seconds' => $timer->extension_seconds + ($minutes * 60),
            ]);
            return $timer->fresh();
        });
    }

    /**
     * Preview the overtime that would be charged if the timer were stopped now,
     * without mutating anything. Used by the UI to decide whether to show the
     * "collect or void" prompt and to render the per-tier breakdown.
     *
     * Returns: [
     *   'seconds'      => int,
     *   'minutes'      => int,        // rounded up so 30s past grace still bills as 1 min
     *   'rate'         => float,      // legacy: the dominant tier's rate (first segment)
     *   'rate_tier'    => string,     // legacy: dominant tier label
     *   'charge'       => float,      // total peso amount across all segments
     *   'segments'     => [           // one entry per contiguous rate tier
     *     [
     *       'tier'    => 'base'|'peak'|'weekend'|'custom_rule',
     *       'label'   => 'Base rate'|'Peak rate'|...,
     *       'seconds' => int,
     *       'minutes' => int,         // ceil
     *       'rate'    => float,       // peso/hour for the segment
     *       'charge'  => float,       // peso amount for the segment
     *     ],
     *   ],
     * ]
     */
    public function previewOvertimeAtStop(BookingTimer $timer): array
    {
        $now  = now();
        $diff = $now->diffInSeconds($timer->scheduled_end_at, false);
        $overtimeSeconds = (int) max(0, -$diff - (int) $timer->grace_period_seconds);

        if ($overtimeSeconds <= 0) {
            return [
                'seconds' => 0, 'minutes' => 0,
                'rate'    => 0.0, 'rate_tier' => 'base',
                'charge'  => 0.0, 'segments' => [],
            ];
        }

        $court = $timer->booking?->court ?? $timer->court;
        $overtimeStart = $timer->scheduled_end_at->copy()->addSeconds((int) $timer->grace_period_seconds);

        $segments    = $this->splitOvertimeByRate($court, $overtimeStart, $now);
        $totalCharge = round(array_sum(array_column($segments, 'charge')), 2);

        // Pick the dominant tier (most seconds) for the badge / legacy single-rate
        // consumers like the SPEC dashboard tile.
        $primary = collect($segments)->sortByDesc('seconds')->first() ?? [
            'tier' => 'base', 'rate' => 0.0,
        ];

        return [
            'seconds'   => $overtimeSeconds,
            'minutes'   => (int) ceil($overtimeSeconds / 60),
            'rate'      => (float) $primary['rate'],
            'rate_tier' => (string) $primary['tier'],
            'charge'    => $totalCharge,
            'segments'  => $segments,
        ];
    }

    /**
     * Walk the overtime window and group contiguous seconds that share the same
     * rate tier (base / peak / weekend / custom pricing rule) into segments.
     *
     * We sample at 60-second boundaries; that gives accurate base↔peak splits at
     * the minute the evening window opens/closes, while keeping the work cheap
     * (≤ a few hundred lookups for any realistic overtime).
     */
    private function splitOvertimeByRate(Court $court, Carbon $start, Carbon $end): array
    {
        if ($end->lte($start)) {
            return [];
        }

        $segments = [];
        $cursor   = $start->copy();
        $segStart = $cursor->copy();
        $segTier  = null;
        $segRate  = null;

        while ($cursor->lt($end)) {
            // Step forward by 60s, but never past $end — the last partial minute
            // still bills correctly because $cursor → $next is the segment slice.
            $next = $cursor->copy()->addSeconds(60);
            if ($next->gt($end)) {
                $next = $end->copy();
            }

            // Use the midpoint of the slice so a slice straddling the peak/base
            // boundary lands cleanly on one side (the dominant half).
            $sample = $cursor->copy()->addSeconds((int) ($cursor->diffInSeconds($next) / 2));
            $rate   = (float) $court->getRateForSlot($sample->toDateTime(), $sample->toDateTime());
            $tier   = $this->classifyRateTier($court, $sample, $rate);

            if ($segTier === null) {
                $segTier = $tier;
                $segRate = $rate;
            }

            // Rate changed — close out the previous segment and start a new one.
            if ($tier !== $segTier || abs($rate - $segRate) > 0.005) {
                $segments[] = $this->buildSegment($segTier, $segRate, $segStart, $cursor);
                $segStart   = $cursor->copy();
                $segTier    = $tier;
                $segRate    = $rate;
            }

            $cursor = $next;
        }

        $segments[] = $this->buildSegment($segTier ?? 'base', $segRate ?? 0.0, $segStart, $end);

        return $segments;
    }

    private function buildSegment(string $tier, float $rate, Carbon $from, Carbon $to): array
    {
        $seconds = (int) max(0, $from->diffInSeconds($to));
        $charge  = round(($seconds / 3600) * $rate, 2);

        return [
            'tier'    => $tier,
            'label'   => match ($tier) {
                'peak'        => 'Peak rate',
                'weekend'     => 'Weekend rate',
                'custom_rule' => 'Custom rule',
                default       => 'Base rate',
            },
            'seconds' => $seconds,
            'minutes' => (int) ceil($seconds / 60),
            'rate'    => $rate,
            'charge'  => $charge,
        ];
    }

    private function classifyRateTier(Court $court, Carbon $when, float $rate): string
    {
        $dow = (int) $when->format('N');
        $weekendRate = (float) ($court->weekend_hourly_rate ?? 0);
        $peakRate    = (float) ($court->peak_hourly_rate ?? 0);

        if (($dow === 6 || $dow === 7) && abs($rate - $weekendRate) < 0.005) return 'weekend';
        if ($peakRate > 0 && abs($rate - $peakRate) < 0.005)                  return 'peak';
        if (abs($rate - (float) $court->base_hourly_rate) < 0.005)            return 'base';
        return 'custom_rule';
    }

    /**
     * Stop the timer and finalize the booking. If overtime accrued past the grace
     * period, the caller decides what happens via $overtimeSettlement:
     *   'collect' — record a paid cash Payment row for the overtime amount,
     *               bump booking total_amount/paid_amount, hit revenue reports.
     *               Settlement audit fields are set to 'paid'.
     *   'void'    — keep overtime_seconds for the audit trail, zero the charge,
     *               no Payment row written. Settlement audit fields are set to
     *               'voided'.
     *   'auto'    — legacy behavior used when no overtime exists or when the
     *               auto-stop-after-grace job closes the session itself. No
     *               settlement decision is needed.
     *
     * $settledBy records which staff user made the paid/void decision (for the
     * audit log + reports). Pass null only for unattended/system paths.
     *
     * $stoppedAt pins the moment the session is treated as ended. Manual stops
     * leave it null (= now). The auto-stop-after-grace path passes the exact
     * grace-expiry instant so the recorded duration never over-counts the lag
     * between grace expiry and whenever the sweep/cron actually fires — and so
     * overtime computes to exactly zero (no charge is ever raised).
     */
    public function stopTimer(BookingTimer $timer, string $overtimeSettlement = 'auto', ?User $settledBy = null, ?Carbon $stoppedAt = null): BookingTimer
    {
        return DB::transaction(function () use ($timer, $overtimeSettlement, $settledBy, $stoppedAt) {
            $now  = $stoppedAt ?? now();
            $diff = $now->diffInSeconds($timer->scheduled_end_at, false);
            $overtimeSeconds = (int) max(0, -$diff - (int) $timer->grace_period_seconds);

            $segments    = [];
            $totalCharge = 0.0;
            $primaryRate = (float) $timer->overtime_rate;

            if ($overtimeSeconds > 0) {
                $court         = $timer->booking?->court ?? $timer->court;
                $overtimeStart = $timer->scheduled_end_at->copy()->addSeconds((int) $timer->grace_period_seconds);

                // Split the actual overtime window into per-tier segments so the
                // booking is billed correctly when overtime crosses the peak/base
                // boundary (e.g. play ran from 5:50 pm into 6:30 pm peak hours).
                $segments    = $this->splitOvertimeByRate($court, $overtimeStart, $now);
                $totalCharge = round(array_sum(array_column($segments, 'charge')), 2);

                // The persisted overtime_rate represents the dominant tier so
                // legacy report views and tiles still render a single rate.
                $primary     = collect($segments)->sortByDesc('seconds')->first();
                $primaryRate = $primary ? (float) $primary['rate'] : 0.0;
            }

            $timer->overtime_seconds = $overtimeSeconds;
            $timer->overtime_rate    = $primaryRate;
            $timer->overtime_charge  = $totalCharge;
            $timer->status           = 'stopped';
            $timer->stopped_at       = $now;
            $timer->save();

            $booking        = $timer->booking;
            $overtimeAmount = (float) $timer->overtime_charge;

            if ($overtimeAmount > 0 && $overtimeSettlement === 'collect') {
                Payment::create([
                    'tenant_id'      => $booking->tenant_id,
                    'customer_id'    => $booking->customer_id,
                    'payable_type'   => Booking::class,
                    'payable_id'     => $booking->id,
                    'payment_number' => 'PAY-' . strtoupper((string) Str::ulid()),
                    'amount'         => $overtimeAmount,
                    'method'         => 'cash',
                    'status'         => 'paid',
                    'paid_at'        => $now,
                    'processed_by'   => $settledBy?->id,
                    'notes'          => $this->formatOvertimeNote($segments, $booking->booking_number),
                ]);

                $booking->increment('total_amount', $overtimeAmount);
                $booking->increment('paid_amount',  $overtimeAmount);

                $timer->update([
                    'overtime_settlement' => 'paid',
                    'overtime_settled_at' => $now,
                    'overtime_settled_by' => $settledBy?->id,
                    'overtime_breakdown'  => $segments,
                ]);

                // Revenue dashboards/reports cache today's & this month's totals —
                // drop them so the just-collected overtime shows up immediately.
                $this->invalidateRevenueCache($booking->tenant_id);
            } elseif ($overtimeAmount > 0 && $overtimeSettlement === 'void') {
                // Keep overtime_seconds + breakdown so the audit/timeline still
                // shows there was overtime, but drop the charge so it doesn't
                // get billed.
                $timer->update([
                    'overtime_charge'     => 0,
                    'overtime_settlement' => 'voided',
                    'overtime_settled_at' => $now,
                    'overtime_settled_by' => $settledBy?->id,
                    'overtime_breakdown'  => $segments,
                ]);
            }

            $booking->update([
                'status'         => 'completed',
                'checked_out_at' => $now,
            ]);

            $booking->court->update(['status' => 'available']);

            return $timer;
        });
    }

    /**
     * The exact instant the free grace window closes for a timer
     * (scheduled end + grace seconds), or null if the timer has no end.
     */
    public function graceExpiresAt(BookingTimer $timer): ?Carbon
    {
        if (!$timer->scheduled_end_at) {
            return null;
        }
        return $timer->scheduled_end_at->copy()->addSeconds((int) $timer->grace_period_seconds);
    }

    /**
     * Whether this timer's tenant has opted into auto-stopping the session the
     * moment the grace period expires. Settings store the toggle as "1"/"0".
     */
    public function tenantWantsAutoStop(BookingTimer $timer): bool
    {
        return (bool) ($timer->booking?->tenant?->settings['auto_stop_after_grace'] ?? false);
    }

    /**
     * Stop a single timer if — and only if — its tenant has auto-stop enabled
     * and the grace window has fully elapsed. Idempotent: a timer that is not
     * running/overtime, whose tenant opted out, or that is still inside grace
     * is left untouched. Returns true when it actually stopped the session.
     *
     * This is the shared entry point used by the every-minute scheduler sweep
     * AND by the live page polls, so auto-stop fires whether or not anyone is
     * watching and whether or not a queue worker is running.
     */
    public function maybeAutoStop(BookingTimer $timer): bool
    {
        if (!in_array($timer->status, ['running', 'overtime'], true)) {
            return false;
        }
        if (!$this->tenantWantsAutoStop($timer)) {
            return false;
        }

        $graceEnd = $this->graceExpiresAt($timer);
        if (!$graceEnd || now()->lt($graceEnd)) {
            return false; // still inside (or before) the grace window
        }

        Log::info('Auto-stopping session: grace period expired', [
            'timer_id'         => $timer->id,
            'booking_id'       => $timer->booking_id,
            'court_id'         => $timer->court_id,
            'scheduled_end_at' => $timer->scheduled_end_at?->toIso8601String(),
            'grace_seconds'    => (int) $timer->grace_period_seconds,
            'grace_expired_at' => $graceEnd->toIso8601String(),
            'detected_at'      => now()->toIso8601String(),
        ]);

        // Pin the stop to the grace-expiry instant: no overtime accrues and the
        // recorded duration is exact regardless of how late the sweep fired.
        $this->stopTimer($timer, 'auto', null, $graceEnd);

        // Push the new state to any listening UI (broadcast) so live timers stop
        // without a manual refresh.
        event(new TimerUpdated($timer->fresh()));

        return true;
    }

    /**
     * Sweep every running/overtime timer that is past its scheduled end and
     * auto-stop the ones whose grace has expired (tenant toggle permitting).
     * Returns the number of sessions stopped. Safe to call frequently — it only
     * touches timers that genuinely need stopping.
     *
     * Pass $tenantId to limit the sweep to one tenant (used by the per-tenant
     * live poll); omit it for the global every-minute scheduler sweep.
     */
    public function autoStopExpiredTimers(?int $tenantId = null): int
    {
        $stopped = 0;

        BookingTimer::with('booking.tenant', 'booking.court', 'court')
            ->whereIn('status', ['running', 'overtime'])
            ->where('scheduled_end_at', '<=', now())
            ->when($tenantId, fn ($q) => $q->whereHas('booking', fn ($b) => $b->where('tenant_id', $tenantId)))
            ->each(function (BookingTimer $timer) use (&$stopped) {
                if ($this->maybeAutoStop($timer)) {
                    $stopped++;
                }
            });

        return $stopped;
    }

    /**
     * Human-readable Payment note for collected overtime, e.g.:
     *   "Overtime: 10 min base @ ₱400/hr + 20 min peak @ ₱600/hr for booking #BK-2026-0042"
     */
    private function formatOvertimeNote(array $segments, string $bookingNumber): string
    {
        $parts = array_map(
            fn ($s) => sprintf(
                '%d min %s @ ₱%s/hr',
                (int) ceil($s['seconds'] / 60),
                $s['tier'],
                number_format((float) $s['rate'], 2)
            ),
            array_filter($segments, fn ($s) => $s['seconds'] > 0)
        );

        return 'Overtime: ' . (empty($parts) ? '0 min' : implode(' + ', $parts))
            . ' for booking #' . $bookingNumber;
    }

    public function getRescheduleOptions(Booking $booking, string $newDate): array
    {
        $court = $booking->court;
        $duration = $booking->duration_minutes;
        $slots = [];

        $start = Carbon::parse($newDate . ' 07:00');
        $end = Carbon::parse($newDate . ' 22:00');

        while ($start->copy()->addMinutes($duration)->lte($end)) {
            $slotEnd = $start->copy()->addMinutes($duration);
            $available = $this->checkAvailability(
                $court->id,
                $newDate,
                $start->format('H:i'),
                $slotEnd->format('H:i')
            );

            if ($available) {
                $slots[] = [
                    'start' => $start->format('H:i'),
                    'end' => $slotEnd->format('H:i'),
                ];
            }

            $start->addMinutes(30);
        }

        return $slots;
    }
}