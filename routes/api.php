<?php

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\BookingApiController;
use App\Http\Controllers\Api\V1\CourtApiController;
use App\Http\Controllers\Api\V1\MembershipApiController;
use App\Http\Controllers\Api\V1\NotificationApiController;
use App\Http\Controllers\Api\V1\PaymentApiController;
use App\Http\Controllers\Api\V1\PaymentWebhookController;
use App\Http\Controllers\Api\V1\WalletApiController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->name('api.v1.')->group(function () {

    // ── Public ──────────────────────────────────────────────────────────────────
    Route::post('/auth/register', [AuthController::class, 'register'])->middleware('throttle:login')->name('auth.register');
    Route::post('/auth/login',    [AuthController::class, 'login'])->middleware('throttle:login')->name('auth.login');

    // Webhooks — tenant-scoped via opaque token in the URL. Each tenant registers
    // their own URL in their PayMongo/Stripe dashboard so we can route the event
    // to the correct tenant and verify with that tenant's webhook secret. Raw
    // body is preserved here for signature verification.
    Route::post('/webhooks/paymongo/{token}', [PaymentWebhookController::class, 'paymongo'])
        ->middleware('throttle:payment-webhook')
        ->where('token', '[A-Za-z0-9]+')
        ->name('webhooks.paymongo');
    Route::post('/webhooks/stripe/{token}', [PaymentWebhookController::class, 'stripe'])
        ->middleware('throttle:payment-webhook')
        ->where('token', '[A-Za-z0-9]+')
        ->name('webhooks.stripe');

    // TV display data (public with ?tenant=slug)
    Route::get('/display', [\App\Http\Controllers\Admin\DisplayController::class, 'data'])->name('display.data');

    // ── Protected (Sanctum) ─────────────────────────────────────────────────────
    Route::middleware(['auth:sanctum', \App\Http\Middleware\EnsureTenantIsActive::class])->group(function () {

        // Auth
        Route::post('/auth/logout', [AuthController::class, 'logout'])->name('auth.logout');
        Route::get('/auth/me',      [AuthController::class, 'me'])->name('auth.me');

        // Courts
        Route::get('/courts',               [CourtApiController::class, 'index'])->name('courts.index');
        Route::get('/courts/status-board',  [CourtApiController::class, 'statusBoard'])->name('courts.status-board');
        Route::get('/courts/{court}',       [CourtApiController::class, 'show'])->name('courts.show');
        Route::get('/courts/{court}/availability', [CourtApiController::class, 'availability'])->name('courts.availability');

        // Bookings
        Route::get('/bookings',                   [BookingApiController::class, 'index'])->name('bookings.index');
        Route::post('/bookings',                  [BookingApiController::class, 'store'])->middleware('throttle:booking-create')->name('bookings.store');
        Route::get('/bookings/availability',      [BookingApiController::class, 'availability'])->name('bookings.availability');
        Route::get('/bookings/conflict-check',    [BookingApiController::class, 'checkConflict'])->name('bookings.conflict');
        Route::get('/bookings/{booking}',         [BookingApiController::class, 'show'])->name('bookings.show');
        Route::post('/bookings/{booking}/cancel', [BookingApiController::class, 'cancel'])->middleware('throttle:booking-create')->name('bookings.cancel');

        // Memberships
        Route::get('/membership-plans',            [MembershipApiController::class, 'plans'])->name('membership-plans.index');
        Route::get('/memberships',                 [MembershipApiController::class, 'index'])->name('memberships.index');
        Route::get('/memberships/active',          [MembershipApiController::class, 'active'])->name('memberships.active');
        Route::post('/memberships',                [MembershipApiController::class, 'subscribe'])->name('memberships.subscribe');
        Route::post('/memberships/{membership}/cancel', [MembershipApiController::class, 'cancel'])->name('memberships.cancel');

        // Payments
        Route::get('/payments',             [PaymentApiController::class, 'index'])->name('payments.index');
        Route::get('/payments/{payment}',   [PaymentApiController::class, 'show'])->name('payments.show');
        Route::post('/payments/intent',     [PaymentApiController::class, 'createIntent'])->name('payments.intent');
        Route::post('/payments/{payment}/refund', [PaymentApiController::class, 'refund'])->name('payments.refund');

        // Wallet — read-only for customers. Top-up is now a manual
        // owner/staff action performed from the admin panel.
        Route::get('/wallet',                  [WalletApiController::class, 'balance'])->name('wallet.balance');
        Route::get('/wallet/transactions',     [WalletApiController::class, 'transactions'])->name('wallet.transactions');

        // Notifications
        Route::get('/notifications',                      [NotificationApiController::class, 'index'])->name('notifications.index');
        Route::post('/notifications/{id}/read',           [NotificationApiController::class, 'markRead'])->name('notifications.read');
        Route::post('/notifications/read-all',            [NotificationApiController::class, 'markAllRead'])->name('notifications.read-all');
        Route::delete('/notifications/{id}',              [NotificationApiController::class, 'destroy'])->name('notifications.destroy');
    });
});
