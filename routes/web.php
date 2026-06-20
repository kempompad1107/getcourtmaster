<?php

use App\Http\Controllers\Admin\BookingController;
use App\Http\Controllers\Admin\BranchContextController;
use App\Http\Controllers\Admin\BranchController;
use App\Http\Controllers\Admin\CourtController;
use App\Http\Controllers\Admin\CustomerController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\DisplayController;
use App\Http\Controllers\Admin\MembershipController;
use App\Http\Controllers\Admin\PosController;
use App\Http\Controllers\Admin\ProductCategoryController;
use App\Http\Controllers\Admin\ProductController;
use App\Http\Controllers\Admin\PromotionController;
use App\Http\Controllers\Admin\ReportController;
use App\Http\Controllers\Admin\SettingsController;
use App\Http\Controllers\Admin\StaffController;
use App\Http\Controllers\Admin\SubscriptionPlanController;
use App\Http\Controllers\Admin\TenantController;
use Illuminate\Support\Facades\Route;

// ─── Public ────────────────────────────────────────────────────────────────────
Route::get('/', function () {
    if ($user = auth()->user()) {
        return redirect(\App\Http\Controllers\Auth\LoginController::landingFor($user));
    }
    return redirect()->route('login');
})->name('home');

// Auth views
Route::middleware('guest')->group(function () {
    Route::get('/login',          fn () => view('auth.login'))->name('login');
    Route::get('/register',       fn () => view('auth.register'))->name('register');
    Route::get('/forgot-password', fn () => view('auth.forgot-password'))->name('password.request');
    Route::get('/reset-password/{token}', fn ($token) => view('auth.reset-password', ['request' => request()]))->name('password.reset');

    // Per-tenant customer signup (the canonical way to register as a player).
    // Tenants share this URL via QR codes / business cards / their own site.
    Route::get('/t/{tenant:slug}/register', [\App\Http\Controllers\Auth\RegisterController::class, 'showForTenant'])
        ->name('register.tenant');
});

// Auth actions (Laravel Breeze / manual)
Route::post('/login', [\App\Http\Controllers\Auth\LoginController::class, 'store'])->middleware('throttle:login')->name('login.store');
Route::post('/t/{tenant:slug}/register', [\App\Http\Controllers\Auth\RegisterController::class, 'storeForTenant'])
    ->middleware('throttle:login')->name('register.tenant.store');
Route::post('/logout', [\App\Http\Controllers\Auth\LoginController::class, 'destroy'])->name('logout')->middleware('auth');
Route::post('/forgot-password', [\App\Http\Controllers\Auth\PasswordResetController::class, 'sendLink'])->middleware('throttle:password-reset')->name('password.email');
Route::post('/reset-password', [\App\Http\Controllers\Auth\PasswordResetController::class, 'reset'])->middleware('throttle:password-reset')->name('password.store');

// Two-factor challenge (mid-login)
Route::get('/2fa/challenge', [\App\Http\Controllers\Auth\TwoFactorController::class, 'challenge'])
    ->middleware('guest')->name('2fa.challenge');
Route::post('/2fa/challenge', [\App\Http\Controllers\Auth\TwoFactorController::class, 'verify'])
    ->middleware(['guest', 'throttle:2fa-verify'])->name('2fa.verify');

// OTP login (passwordless)
Route::middleware('guest')->group(function () {
    Route::get('/otp',          [\App\Http\Controllers\Auth\OtpLoginController::class, 'show'])->name('otp.request');
    Route::post('/otp',         [\App\Http\Controllers\Auth\OtpLoginController::class, 'send'])->name('otp.send');
    Route::get('/otp/verify',   [\App\Http\Controllers\Auth\OtpLoginController::class, 'verifyForm'])->name('otp.verify.show');
    Route::post('/otp/verify',  [\App\Http\Controllers\Auth\OtpLoginController::class, 'verify'])
        ->middleware('throttle:2fa-verify')->name('otp.verify');
});

// Social auth
Route::get('/auth/google',          [\App\Http\Controllers\Auth\SocialiteController::class, 'redirectToGoogle'])->name('auth.google');
Route::get('/auth/google/callback', [\App\Http\Controllers\Auth\SocialiteController::class, 'handleGoogleCallback'])->name('auth.google.callback');
Route::get('/auth/facebook',          [\App\Http\Controllers\Auth\SocialiteController::class, 'redirectToFacebook'])->name('auth.facebook');
Route::get('/auth/facebook/callback', [\App\Http\Controllers\Auth\SocialiteController::class, 'handleFacebookCallback'])->name('auth.facebook.callback');

// Public tenant landing page (the shareable URL customers visit before signup).
Route::get('/t/{tenant:slug}', [\App\Http\Controllers\TenantPublicController::class, 'show'])
    ->name('tenant.public');

// TV display (public with tenant slug). Throttled so the slug can't be used to
// poll/enumerate court state at high rate (M-2).
Route::middleware('throttle:60,1')->group(function () {
    Route::get('/display', [DisplayController::class, 'index'])->name('display.public');
    Route::get('/display/data', [DisplayController::class, 'data'])->name('display.data');
});

// Tenant status pages
Route::view('/suspended', 'errors.suspended')->name('tenant.suspended');
Route::view('/cancelled', 'errors.cancelled')->name('tenant.cancelled');
Route::view('/trial-expired', 'errors.trial-expired')->name('tenant.trial-expired');
Route::view('/offline', 'errors.offline')->name('offline');

// ─── Authenticated ─────────────────────────────────────────────────────────────
Route::middleware(['auth', \App\Http\Middleware\SetTenantContext::class, \App\Http\Middleware\TrackUserSession::class])->group(function () {

    // ── Super Admin ─────────────────────────────────────────────────────────────
    Route::prefix('super')->name('super.')->middleware('role:super_admin')->group(function () {
        Route::get('/dashboard', [DashboardController::class, 'superAdmin'])->name('dashboard');

        // Tenant management
        Route::get('/tenants',                       [TenantController::class, 'index'])->name('tenants.index');
        Route::get('/tenants/create',                [TenantController::class, 'create'])->name('tenants.create');
        Route::post('/tenants',                      [TenantController::class, 'store'])->name('tenants.store');
        Route::get('/tenants/{tenant}',              [TenantController::class, 'show'])->name('tenants.show');
        Route::get('/tenants/{tenant}/edit',         [TenantController::class, 'edit'])->name('tenants.edit');
        Route::put('/tenants/{tenant}',              [TenantController::class, 'update'])->name('tenants.update');
        Route::post('/tenants/{tenant}/suspend',      [TenantController::class, 'suspend'])->name('tenants.suspend');
        Route::post('/tenants/{tenant}/activate',     [TenantController::class, 'activate'])->name('tenants.activate');
        Route::post('/tenants/{tenant}/set-trial',    [TenantController::class, 'setTrial'])->name('tenants.set-trial');
        Route::post('/tenants/{tenant}/extend-trial', [TenantController::class, 'extendTrial'])->name('tenants.extend-trial');
        Route::post('/tenants/{tenant}/cancel',       [TenantController::class, 'cancel'])->name('tenants.cancel');
        Route::delete('/tenants/{tenant}',            [TenantController::class, 'destroy'])->name('tenants.destroy');
        Route::post('/tenants/{tenant}/impersonate',  [TenantController::class, 'impersonate'])->name('tenants.impersonate');
        Route::post('/tenants/{tenant}/toggle-demo',  [TenantController::class, 'toggleDemo'])->name('tenants.toggle-demo');
        Route::post('/tenants/{tenant}/reset-demo',   [TenantController::class, 'resetDemoData'])->name('tenants.reset-demo');

        // Tenant users (per-tenant)
        Route::get('/tenants/{tenant}/users',                              [TenantController::class, 'users'])->name('tenants.users');
        Route::post('/tenants/{tenant}/users/{user}/reset-password',       [TenantController::class, 'resetPassword'])->name('tenants.users.reset-password');
        Route::post('/tenants/{tenant}/users/{user}/disable-2fa',          [TenantController::class, 'disableTwoFactor'])->name('tenants.users.disable-2fa');

        // Subscription plans
        Route::resource('plans', SubscriptionPlanController::class)->names([
            'index'   => 'plans.index',
            'create'  => 'plans.create',
            'store'   => 'plans.store',
            'show'    => 'plans.show',
            'edit'    => 'plans.edit',
            'update'  => 'plans.update',
            'destroy' => 'plans.destroy',
        ]);

        // System reports (SaaS-level analytics)
        Route::get('/reports', [\App\Http\Controllers\SuperAdmin\SystemReportsController::class, 'index'])->name('reports.index');

        // Platform settings — PayMongo / Stripe credentials for subscription billing
        Route::get('/settings',          [\App\Http\Controllers\SuperAdmin\SettingsController::class, 'index'])->name('settings.index');
        Route::put('/settings/gateways', [\App\Http\Controllers\SuperAdmin\SettingsController::class, 'updateGateways'])->name('settings.gateways');
        Route::put('/settings/branding', [\App\Http\Controllers\SuperAdmin\SettingsController::class, 'updateBranding'])->name('settings.branding');

        // Billing — cross-tenant invoices, plan changes, manual payments
        Route::prefix('billing')->name('billing.')->group(function () {
            Route::get('/invoices',                                  [\App\Http\Controllers\SuperAdmin\BillingController::class, 'invoices'])->name('invoices');
            Route::post('/invoices/{invoice}/mark-paid',             [\App\Http\Controllers\SuperAdmin\BillingController::class, 'markInvoicePaid'])->name('invoices.mark-paid');
            Route::post('/invoices/{invoice}/retry',                 [\App\Http\Controllers\SuperAdmin\BillingController::class, 'retryInvoice'])->name('invoices.retry');
            Route::post('/subscriptions/{subscription}/generate',    [\App\Http\Controllers\SuperAdmin\BillingController::class, 'generateInvoice'])->name('subscriptions.generate');
            Route::post('/subscriptions/{subscription}/cancel',      [\App\Http\Controllers\SuperAdmin\BillingController::class, 'cancelSubscription'])->name('subscriptions.cancel');
            Route::post('/tenants/{tenant}/change-plan',             [\App\Http\Controllers\SuperAdmin\BillingController::class, 'changePlan'])->name('tenants.change-plan');
        });
    });

    // Return to super-admin after impersonating a tenant owner. Outside the
    // super.* group because the request is made while logged in as the owner.
    Route::post('/stop-impersonating', [TenantController::class, 'stopImpersonating'])->name('impersonate.stop');

    // ── Owner subscription self-service ──────────────────────────────────────────
    // Deliberately OUTSIDE EnsureTenantIsActive so a suspended owner can still reach
    // this page to pay and reactivate. Owner-only is enforced in the controller.
    Route::prefix('admin')->name('admin.')->middleware('staff.only')->group(function () {
        Route::get('/subscription',                 [\App\Http\Controllers\Admin\SubscriptionController::class, 'index'])->name('subscription');
        Route::post('/subscription/change-plan',    [\App\Http\Controllers\Admin\SubscriptionController::class, 'changePlan'])->name('subscription.change-plan');
        Route::post('/subscription/renew',          [\App\Http\Controllers\Admin\SubscriptionController::class, 'renew'])->name('subscription.renew');
        Route::post('/subscription/invoices/{invoice}/checkout', [\App\Http\Controllers\Admin\SubscriptionController::class, 'checkout'])->name('subscription.checkout');
        Route::get('/subscription/checkout/return', [\App\Http\Controllers\Admin\SubscriptionController::class, 'checkoutReturn'])->name('subscription.checkout.return');
    });

    // ── Admin / Business Owner / Staff ──────────────────────────────────────────
    Route::prefix('admin')->name('admin.')->middleware(['staff.only', \App\Http\Middleware\EnsureTenantIsActive::class, 'branch.context'])->group(function () {

        Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

        // Dismiss a plan-limit banner for the current login session
        Route::post('/plan-banner/dismiss', function (\Illuminate\Http\Request $request) {
            $resource = (string) $request->input('resource', '');
            if ($resource !== '') {
                $dismissed = $request->session()->get('plan_banner_dismissed', []);
                $dismissed[$resource] = true;
                $request->session()->put('plan_banner_dismissed', $dismissed);
            }
            return response()->noContent();
        })->name('plan-banner.dismiss');

        // Branch context switcher
        Route::post('/branch-context', [BranchContextController::class, 'update'])->name('branch-context.update');

        // Branches
        Route::middleware('perm:branches.view')->group(function () {
            Route::resource('branches', BranchController::class)->except(['show']);
        });

        // Courts
        Route::middleware('perm:courts.view')->group(function () {
        Route::get('/courts/status-board',  [CourtController::class, 'statusBoard'])->name('courts.status-board');
        Route::get('/courts/timer-state',   [CourtController::class, 'timerState'])->name('courts.timer-state');
        Route::get('/courts/{court}/availability', [CourtController::class, 'availability'])->name('courts.availability');
        Route::get('/courts/{court}/timeline', [CourtController::class, 'timeline'])->name('courts.timeline');
        Route::get('/courts/{court}/check', [CourtController::class, 'check'])->name('courts.check');
        Route::patch('/courts/{court}/status', [CourtController::class, 'updateStatus'])->name('courts.status')->middleware('branch.required');
        Route::delete('/courts/{court}/media/{mediaId}', [CourtController::class, 'destroyMedia'])->name('courts.media.destroy')->middleware('branch.required');
        Route::resource('courts', CourtController::class)->middleware('branch.required');
        });

        // Bookings
        Route::middleware('perm:bookings.view')->group(function () {
        Route::get('/bookings/calendar', [BookingController::class, 'calendar'])->name('bookings.calendar');
        Route::get('/bookings/calendar-data', [BookingController::class, 'calendarData'])->name('bookings.calendar-data');
        Route::patch('/bookings/{booking}/confirm', [BookingController::class, 'confirm'])->name('bookings.confirm')->middleware('branch.required');
        Route::patch('/bookings/{booking}/cancel', [BookingController::class, 'cancel'])->name('bookings.cancel')->middleware('branch.required');
        Route::post('/bookings/{booking}/approve',  [BookingController::class, 'approve'])->name('bookings.approve')->middleware('branch.required');
        Route::post('/bookings/{booking}/deny',     [BookingController::class, 'deny'])->name('bookings.deny')->middleware('branch.required');
        Route::patch('/bookings/{booking}/reschedule', [BookingController::class, 'reschedule'])->name('bookings.reschedule')->middleware('branch.required');
        Route::post('/bookings/{booking}/collect-cash', [BookingController::class, 'collectCash'])->name('bookings.collect-cash')->middleware('branch.required');
        Route::post('/bookings/walk-in',         [BookingController::class, 'walkIn'])->name('bookings.walk-in')->middleware('branch.required');
        Route::post('/bookings/walk-in/preview', [BookingController::class, 'walkInPreview'])->name('bookings.walk-in.preview')->middleware('branch.required');
        Route::get('/bookings/{booking}/receipt', [BookingController::class, 'receipt'])->name('bookings.receipt');

        // Timer endpoints
        // Live state poll for the booking page — also enforces auto-stop-after-grace
        // for this booking so an open page stops itself without a manual refresh.
        Route::get('/bookings/{booking}/timer/state', [BookingController::class, 'bookingTimerState'])->name('bookings.timer.state');
        Route::post('/bookings/{booking}/timer/start', [BookingController::class, 'startTimer'])->name('bookings.timer.start')->middleware('branch.required');
        Route::post('/bookings/{booking}/timer/extend', [BookingController::class, 'extendBookingTimer'])->name('bookings.timer.extend')->middleware('branch.required');
        Route::post('/bookings/{booking}/timer/stop', [BookingController::class, 'stopBookingTimer'])->name('bookings.timer.stop')->middleware('branch.required');
        Route::post('/timers/{timer}/pause', [BookingController::class, 'pauseTimer'])->name('timers.pause')->middleware('branch.required');
        Route::post('/timers/{timer}/resume', [BookingController::class, 'resumeTimer'])->name('timers.resume')->middleware('branch.required');
        Route::post('/timers/{timer}/extend', [BookingController::class, 'extendTimer'])->name('timers.extend')->middleware('branch.required');
        Route::post('/timers/{timer}/stop', [BookingController::class, 'stopTimer'])->name('timers.stop')->middleware('branch.required');

        Route::resource('bookings', BookingController::class)->except(['edit', 'update', 'destroy'])->middleware('branch.required');
        });

        // POS
        Route::prefix('pos')->name('pos.')->middleware(['branch.required', 'perm:pos.access'])->group(function () {
            Route::get('/', [PosController::class, 'index'])->name('index');
            Route::post('/orders', [PosController::class, 'store'])->name('store');
            Route::get('/orders/{order}/receipt', [PosController::class, 'receipt'])->name('receipt');
            Route::get('/orders/{order}/thermal', [PosController::class, 'thermalReceipt'])->name('thermal');
            Route::post('/orders/{order}/void', [PosController::class, 'void'])->name('void');
            Route::post('/orders/{order}/payment', [PosController::class, 'addPayment'])->name('payment');
            Route::get('/history', [PosController::class, 'history'])->name('history');
            Route::post('/barcode', [PosController::class, 'barcode'])->name('barcode');
            Route::get('/drawer', [PosController::class, 'drawerSummary'])->name('drawer.summary');
            Route::post('/drawer', [PosController::class, 'drawerAction'])->name('drawer.action');
        });

        // Memberships
        Route::middleware('perm:memberships.view')->group(function () {
        Route::get('/memberships/plans', [MembershipController::class, 'plans'])->name('memberships.plans');
        Route::post('/memberships/plans', [MembershipController::class, 'storePlan'])->name('memberships.plans.store')->middleware('branch.required');
        Route::put('/memberships/plans/{plan}', [MembershipController::class, 'updatePlan'])->name('memberships.plans.update')->middleware('branch.required');
        Route::delete('/memberships/plans/{plan}', [MembershipController::class, 'destroyPlan'])->name('memberships.plans.destroy')->middleware('branch.required');
        Route::post('/memberships/{membership}/freeze', [MembershipController::class, 'freeze'])->name('memberships.freeze')->middleware('branch.required');
        Route::post('/memberships/{membership}/cancel', [MembershipController::class, 'cancel'])->name('memberships.cancel')->middleware('branch.required');
        Route::post('/memberships/{membership}/renew', [MembershipController::class, 'renew'])->name('memberships.renew')->middleware('branch.required');
        Route::post('/memberships/{membership}/toggle-auto-renew', [MembershipController::class, 'toggleAutoRenew'])->name('memberships.toggle-auto-renew');
        Route::resource('memberships', MembershipController::class)->only(['index', 'store', 'show'])->middleware('branch.required');
        });

        // Reports
        Route::prefix('reports')->name('reports.')->middleware('perm:reports.view')->group(function () {
            Route::get('/', [ReportController::class, 'index'])->name('index');

            // Core JSON endpoints (legacy names kept for backward compat)
            Route::get('/revenue',   [ReportController::class, 'revenue'])->name('revenue');
            Route::get('/occupancy', [ReportController::class, 'occupancy'])->name('occupancy');
            Route::get('/financial', [ReportController::class, 'financial'])->name('financial');
            Route::get('/customers', [ReportController::class, 'customers'])->name('customers');

            // Reports & Analytics module endpoints
            Route::get('/bookings',                  [ReportController::class, 'bookings'])->name('bookings');
            Route::get('/courts',                    [ReportController::class, 'courts'])->name('courts');
            Route::get('/members',                   [ReportController::class, 'members'])->name('members');
            Route::get('/payments',                  [ReportController::class, 'payments'])->name('payments');
            Route::get('/audit',                     [ReportController::class, 'audit'])->name('audit');
            Route::get('/behavior',                  [ReportController::class, 'behavior'])->name('behavior');
            Route::get('/refunds',                   [ReportController::class, 'refunds'])->name('refunds');
            Route::get('/revenue-by-period',         [ReportController::class, 'revenueByPeriod'])->name('revenue-by-period');
            Route::get('/revenue-by-branch',         [ReportController::class, 'revenueByBranch'])->name('revenue-by-branch');
            Route::get('/revenue-by-court',          [ReportController::class, 'revenueByCourt'])->name('revenue-by-court');
            Route::get('/revenue-by-booking-type',   [ReportController::class, 'revenueByBookingType'])->name('revenue-by-booking-type');
            Route::get('/subscription-revenue',      [ReportController::class, 'subscriptionRevenue'])->name('subscription-revenue');

            // Exports
            Route::post('/export',      [ReportController::class, 'export'])->name('export'); // async legacy
            Route::get('/pdf',          [ReportController::class, 'downloadPdf'])->name('pdf');
            Route::get('/spreadsheet',  [ReportController::class, 'downloadSpreadsheet'])->name('spreadsheet');

            // Saved presets
            Route::post('/presets',                  [ReportController::class, 'presetsStore'])->name('presets.store');
            Route::delete('/presets/{preset}',       [ReportController::class, 'presetsDestroy'])->name('presets.destroy');
        });

        // Staff & Shifts — staff management requires staff.view; personal
        // shift/clock routes stay open to every staff member.
        Route::resource('staff', StaffController::class)->middleware(['branch.required', 'perm:staff.view']);
        Route::get('/my-shift', [StaffController::class, 'myShift'])->name('staff.my-shift');
        Route::get('/my-shift/history', [StaffController::class, 'myShiftHistory'])->name('staff.my-shift.history');
        Route::get('/shifts', [StaffController::class, 'shifts'])->name('staff.shifts')->middleware('perm:staff.view');
        Route::post('/shifts', [StaffController::class, 'storeShift'])->name('shifts.store')->middleware(['branch.required', 'perm:staff.view']);
        Route::put('/shifts/{shift}', [StaffController::class, 'updateShift'])->name('shifts.update')->middleware(['branch.required', 'perm:staff.view']);
        Route::post('/staff/clock-in', [StaffController::class, 'clockIn'])->name('staff.clock-in')->middleware('branch.required');
        Route::post('/staff/clock-out', [StaffController::class, 'clockOut'])->name('staff.clock-out')->middleware('branch.required');

        // Inventory
        Route::middleware('perm:inventory.view')->group(function () {
        Route::get('/products/{product}/movements', [ProductController::class, 'movements'])->name('products.movements');
        Route::post('/products/{product}/adjust', [ProductController::class, 'adjustStock'])->name('products.adjust')->middleware('branch.required');
        Route::resource('products', ProductController::class)->middleware('branch.required');
        Route::resource('categories', ProductCategoryController::class)->except(['show'])->middleware('branch.required');
        });

        // Invoices + Receipts (PDF)
        Route::get('/subscription-invoices',               [\App\Http\Controllers\Admin\InvoiceController::class, 'index'])->name('subscription-invoices.index');
        Route::get('/subscription-invoices/{invoice}/pdf', [\App\Http\Controllers\Admin\InvoiceController::class, 'downloadInvoice'])->name('subscription-invoices.pdf');
        Route::get('/payments/{payment}/receipt-pdf',      [\App\Http\Controllers\Admin\InvoiceController::class, 'downloadReceipt'])->name('payments.receipt-pdf');
        Route::post('/payments/{payment}/proof',           [\App\Http\Controllers\Admin\InvoiceController::class, 'uploadProof'])->name('payments.proof');
        Route::post('/payments/{payment}/verify',          [\App\Http\Controllers\Admin\InvoiceController::class, 'verifyProof'])->name('payments.verify');

        // Suppliers + Purchase Orders
        Route::middleware('perm:inventory.view')->group(function () {
        Route::resource('suppliers', \App\Http\Controllers\Admin\SupplierController::class)->except(['show', 'create', 'edit'])->middleware('branch.required');
        Route::get('/purchase-orders',                       [\App\Http\Controllers\Admin\PurchaseOrderController::class, 'index'])->name('purchase-orders.index');
        Route::get('/purchase-orders/create',                [\App\Http\Controllers\Admin\PurchaseOrderController::class, 'create'])->name('purchase-orders.create');
        Route::post('/purchase-orders',                      [\App\Http\Controllers\Admin\PurchaseOrderController::class, 'store'])->name('purchase-orders.store')->middleware('branch.required');
        Route::get('/purchase-orders/{purchase_order}',      [\App\Http\Controllers\Admin\PurchaseOrderController::class, 'show'])->name('purchase-orders.show');
        Route::post('/purchase-orders/{purchase_order}/receive', [\App\Http\Controllers\Admin\PurchaseOrderController::class, 'receive'])->name('purchase-orders.receive')->middleware('branch.required');
        });

        // Promotions
        Route::post('/promotions/validate', [PromotionController::class, 'validate'])->name('promotions.validate');
        Route::resource('promotions', PromotionController::class)->middleware(['branch.required', 'perm:promotions.view']);

        // Customers
        Route::middleware('perm:customers.view')->group(function () {
        Route::get('/customers/search', [CustomerController::class, 'search'])->name('customers.search');
        Route::post('/customers/{user}/credit', [CustomerController::class, 'addWalletCredit'])->name('customers.credit')->middleware('branch.required');
        Route::post('/customers/{user}/debit',  [CustomerController::class, 'debitWallet'])->name('customers.debit')->middleware('branch.required');
        Route::post('/customers/{user}/note', [CustomerController::class, 'addNote'])->name('customers.note')->middleware('branch.required');
        Route::resource('customers', CustomerController::class)->only(['index', 'show', 'create', 'store', 'edit', 'update']);
        });

        // Wallet management (owner/staff only) — manual top-ups, deductions, audit
        Route::middleware('perm:customers.view')->group(function () {
            Route::get('/wallet',                [\App\Http\Controllers\Admin\WalletController::class, 'index'])->name('wallet.index');
            Route::get('/wallet/{customer}',     [\App\Http\Controllers\Admin\WalletController::class, 'show'])->name('wallet.show');
        });

        // Cash refund requests — created automatically when a cash booking is
        // cancelled with refund enabled. Staff settle these at the desk.
        Route::middleware('perm:customers.view')->group(function () {
            Route::get('/refund-requests',                              [\App\Http\Controllers\Admin\RefundRequestController::class, 'index'])->name('refund-requests.index');
            Route::post('/refund-requests/{refundRequest}/process',     [\App\Http\Controllers\Admin\RefundRequestController::class, 'process'])->name('refund-requests.process');
            Route::post('/refund-requests/{refundRequest}/deny',        [\App\Http\Controllers\Admin\RefundRequestController::class, 'deny'])->name('refund-requests.deny');
        });

        // Tournaments
        Route::prefix('tournaments')->name('tournaments.')->middleware('perm:tournaments.view')->group(function () {
            Route::get('/dashboard', [\App\Http\Controllers\Admin\TournamentDashboardController::class, 'index'])->name('dashboard');

            // Global module pages (fixed paths must precede the {tournament} wildcard)
            Route::get('/divisions', [\App\Http\Controllers\Admin\TournamentDivisionController::class, 'index'])->name('divisions.index');
            Route::get('/brackets',  [\App\Http\Controllers\Admin\TournamentBracketController::class, 'index'])->name('brackets.index');
            Route::get('/teams',     [\App\Http\Controllers\Admin\TournamentTeamController::class, 'index'])->name('teams.index');
            Route::get('/matches',   [\App\Http\Controllers\Admin\TournamentMatchController::class, 'index'])->name('matches.index');
            Route::get('/rankings',  [\App\Http\Controllers\Admin\TournamentRankingController::class, 'index'])->name('rankings.index');
            Route::get('/reports',   [\App\Http\Controllers\Admin\TournamentReportController::class, 'index'])->name('reports.index');

            // Division-scoped
            Route::put('/divisions/{division}',    [\App\Http\Controllers\Admin\TournamentDivisionController::class, 'update'])->name('divisions.update');
            Route::delete('/divisions/{division}', [\App\Http\Controllers\Admin\TournamentDivisionController::class, 'destroy'])->name('divisions.destroy');
            Route::post('/divisions/{division}/teams', [\App\Http\Controllers\Admin\TournamentTeamController::class, 'store'])->name('teams.store');
            Route::get('/divisions/{division}/bracket',            [\App\Http\Controllers\Admin\TournamentBracketController::class, 'show'])->name('brackets.show');
            Route::post('/divisions/{division}/bracket/generate',  [\App\Http\Controllers\Admin\TournamentBracketController::class, 'generate'])->name('brackets.generate');
            Route::delete('/divisions/{division}/bracket',         [\App\Http\Controllers\Admin\TournamentBracketController::class, 'reset'])->name('brackets.reset');
            Route::post('/divisions/{division}/bracket/seeds',     [\App\Http\Controllers\Admin\TournamentBracketController::class, 'seeds'])->name('brackets.seeds');
            Route::post('/divisions/{division}/bracket/seed-knockout', [\App\Http\Controllers\Admin\TournamentBracketController::class, 'seedKnockout'])->name('brackets.seed-knockout');
            Route::get('/divisions/{division}/standings', [\App\Http\Controllers\Admin\TournamentRankingController::class, 'show'])->name('rankings.show');

            // Team-scoped
            Route::put('/teams/{team}',           [\App\Http\Controllers\Admin\TournamentTeamController::class, 'update'])->name('teams.update');
            Route::post('/teams/{team}/withdraw', [\App\Http\Controllers\Admin\TournamentTeamController::class, 'withdraw'])->name('teams.withdraw');
            Route::post('/teams/{team}/collect',  [\App\Http\Controllers\Admin\TournamentTeamController::class, 'collectFee'])->name('teams.collect');
            Route::delete('/teams/{team}',        [\App\Http\Controllers\Admin\TournamentTeamController::class, 'destroy'])->name('teams.destroy');

            // Match-scoped
            Route::put('/matches/{match}',           [\App\Http\Controllers\Admin\TournamentMatchController::class, 'update'])->name('matches.update');
            Route::post('/matches/{match}/score',    [\App\Http\Controllers\Admin\TournamentMatchController::class, 'score'])->name('matches.score');
            Route::post('/matches/{match}/walkover', [\App\Http\Controllers\Admin\TournamentMatchController::class, 'walkover'])->name('matches.walkover');
            Route::post('/matches/{match}/status',   [\App\Http\Controllers\Admin\TournamentMatchController::class, 'status'])->name('matches.status');
            Route::post('/matches/{match}/undo',     [\App\Http\Controllers\Admin\TournamentMatchController::class, 'undo'])->name('matches.undo');

            // Tournament CRUD + lifecycle
            Route::get('/',        [\App\Http\Controllers\Admin\TournamentController::class, 'index'])->name('index');
            Route::get('/create',  [\App\Http\Controllers\Admin\TournamentController::class, 'create'])->name('create');
            Route::post('/',       [\App\Http\Controllers\Admin\TournamentController::class, 'store'])->name('store');
            Route::get('/{tournament}',      [\App\Http\Controllers\Admin\TournamentController::class, 'show'])->name('show');
            Route::get('/{tournament}/edit', [\App\Http\Controllers\Admin\TournamentController::class, 'edit'])->name('edit');
            Route::put('/{tournament}',      [\App\Http\Controllers\Admin\TournamentController::class, 'update'])->name('update');
            Route::delete('/{tournament}',   [\App\Http\Controllers\Admin\TournamentController::class, 'destroy'])->name('destroy');
            Route::post('/{tournament}/duplicate', [\App\Http\Controllers\Admin\TournamentController::class, 'duplicate'])->name('duplicate');
            Route::post('/{tournament}/publish',   [\App\Http\Controllers\Admin\TournamentController::class, 'publish'])->name('publish');
            Route::post('/{tournament}/archive',   [\App\Http\Controllers\Admin\TournamentController::class, 'archive'])->name('archive');
            Route::post('/{tournament}/status',    [\App\Http\Controllers\Admin\TournamentController::class, 'updateStatus'])->name('status');
            Route::put('/{tournament}/settings',   [\App\Http\Controllers\Admin\TournamentController::class, 'updateSettings'])->name('settings.update');
            Route::post('/{tournament}/divisions', [\App\Http\Controllers\Admin\TournamentDivisionController::class, 'store'])->name('divisions.store');
            Route::get('/{tournament}/reports/{type}',        [\App\Http\Controllers\Admin\TournamentReportController::class, 'show'])->name('reports.show');
            Route::get('/{tournament}/reports/{type}/export', [\App\Http\Controllers\Admin\TournamentReportController::class, 'export'])->name('reports.export');
        });

        // Settings
        Route::get('/settings', [SettingsController::class, 'index'])->name('settings.index');
        Route::put('/settings/general', [SettingsController::class, 'updateGeneral'])->name('settings.general');
        Route::put('/settings/booking', [SettingsController::class, 'updateBooking'])->name('settings.booking');
        Route::put('/settings/notifications', [SettingsController::class, 'updateNotifications'])->name('settings.notifications');
        Route::put('/settings/gateways', [SettingsController::class, 'updateGateways'])->name('settings.gateways');
        Route::get('/settings/gateways/setup-guide', [SettingsController::class, 'gatewaySetupGuide'])->name('settings.gateways.guide');
        Route::put('/settings/email',       [SettingsController::class, 'updateEmail'])->name('settings.email');
        Route::post('/settings/email/test', [SettingsController::class, 'testMail'])->name('settings.email.test');

        // Roles & Permissions (owner only — enforced in controller)
        Route::get('/settings/roles',                [\App\Http\Controllers\Admin\RoleController::class, 'index'])->name('roles.index');
        Route::get('/settings/roles/{role}/edit',    [\App\Http\Controllers\Admin\RoleController::class, 'edit'])->name('roles.edit');
        Route::put('/settings/roles/{role}',         [\App\Http\Controllers\Admin\RoleController::class, 'update'])->name('roles.update');

        // Smart Display / TV Mode
        Route::middleware('perm:courts.view,bookings.view')->group(function () {
            Route::get('/display', [DisplayController::class, 'index'])->name('display.index');
            Route::get('/display/data', [DisplayController::class, 'data'])->name('display.data');
        });

        // Audit log
        Route::middleware('perm:reports.view')->group(function () {
            Route::get('/audit', [\App\Http\Controllers\Admin\ActivityLogController::class, 'index'])->name('audit.index');
        });

        // Analytics JSON for dashboard charts
        Route::get('/analytics/overview', [\App\Http\Controllers\Admin\AnalyticsController::class, 'overview'])->name('analytics.overview');
    });

    // ── Notifications (shared across portals) ──────────────────────────────────
    Route::get('/notifications',                   [\App\Http\Controllers\NotificationController::class, 'index'])->name('notifications.index');
    Route::get('/notifications/dropdown',          [\App\Http\Controllers\NotificationController::class, 'dropdown'])->name('notifications.dropdown');
    Route::post('/notifications/{id}/read',        [\App\Http\Controllers\NotificationController::class, 'markRead'])->name('notifications.read');
    Route::post('/notifications/read-all',         [\App\Http\Controllers\NotificationController::class, 'markAllRead'])->name('notifications.read-all');
    Route::post('/notifications/push/subscribe',   [\App\Http\Controllers\NotificationController::class, 'subscribePush'])->name('notifications.push.subscribe');
    Route::post('/notifications/push/unsubscribe', [\App\Http\Controllers\NotificationController::class, 'unsubscribePush'])->name('notifications.push.unsubscribe');

    // ── Customer Portal ─────────────────────────────────────────────────────────
    // EnsureTenantIsActive blocks customers of suspended/cancelled/expired-trial
    // venues — and tenant-less "orphan" accounts (e.g. a bare social login) —
    // from using the portal, matching the admin + API guards.
    Route::prefix('app')->name('customer.')->middleware(\App\Http\Middleware\EnsureTenantIsActive::class)->group(function () {
        Route::get('/dashboard', [\App\Http\Controllers\Customer\DashboardController::class, 'index'])->name('dashboard');

        Route::get('/bookings',                         [\App\Http\Controllers\Customer\BookingController::class, 'index'])->name('bookings.index');
        Route::get('/bookings/create',                  [\App\Http\Controllers\Customer\BookingController::class, 'create'])->name('bookings.create');
        Route::post('/bookings',                        [\App\Http\Controllers\Customer\BookingController::class, 'store'])->middleware('throttle:booking-create')->name('bookings.store');
        Route::get('/bookings/{booking}',               [\App\Http\Controllers\Customer\BookingController::class, 'show'])->name('bookings.show');
        Route::get('/bookings/{booking}/payment/return', [\App\Http\Controllers\Customer\BookingController::class, 'paymentReturn'])->name('bookings.payment.return');
        Route::post('/bookings/{booking}/cancel',       [\App\Http\Controllers\Customer\BookingController::class, 'cancel'])->middleware('throttle:booking-create')->name('bookings.cancel');
        Route::get('/courts/{court}/availability',      [\App\Http\Controllers\Customer\BookingController::class, 'availability'])->name('courts.availability');
        Route::get('/courts/{court}/timeline',          [\App\Http\Controllers\Customer\BookingController::class, 'timeline'])->name('courts.timeline');
        Route::get('/courts/{court}/check',             [\App\Http\Controllers\Customer\BookingController::class, 'check'])->name('courts.check');
        Route::post('/promotions/validate',             [\App\Http\Controllers\Customer\BookingController::class, 'validatePromo'])->name('promotions.validate');

        // Tournaments — partner-search must precede the {tournament:slug} wildcard
        Route::get('/tournaments',                      [\App\Http\Controllers\Customer\TournamentController::class, 'index'])->name('tournaments.index');
        Route::get('/tournaments/partner-search',       [\App\Http\Controllers\Customer\TournamentController::class, 'partnerSearch'])->middleware('throttle:tournament-register')->name('tournaments.partner-search');
        Route::post('/tournaments/divisions/{division}/register', [\App\Http\Controllers\Customer\TournamentController::class, 'register'])->middleware('throttle:tournament-register')->name('tournaments.register');
        Route::post('/tournaments/teams/{team}/withdraw',         [\App\Http\Controllers\Customer\TournamentController::class, 'withdraw'])->middleware('throttle:tournament-register')->name('tournaments.withdraw');
        Route::get('/tournaments/{tournament:slug}',    [\App\Http\Controllers\Customer\TournamentController::class, 'show'])->name('tournaments.show');
        Route::get('/tournaments/{tournament:slug}/divisions/{division}/bracket', [\App\Http\Controllers\Customer\TournamentController::class, 'bracket'])->name('tournaments.bracket');

        Route::get('/wallet',                          [\App\Http\Controllers\Customer\WalletController::class, 'index'])->name('wallet.index');
        Route::post('/wallet/topup',                   [\App\Http\Controllers\Customer\WalletController::class, 'topup'])->name('wallet.topup');
        Route::get('/wallet/topup/{payment}/return',   [\App\Http\Controllers\Customer\WalletController::class, 'topupReturn'])->name('wallet.topup.return');
        Route::get('/memberships',                             [\App\Http\Controllers\Customer\MembershipController::class, 'index'])->name('memberships.index');
        Route::post('/memberships/plans/{plan}/subscribe',     [\App\Http\Controllers\Customer\MembershipController::class, 'subscribe'])->name('memberships.subscribe');
        Route::post('/memberships/{membership}/renew',         [\App\Http\Controllers\Customer\MembershipController::class, 'renew'])->name('memberships.renew');
        Route::post('/memberships/{membership}/freeze',        [\App\Http\Controllers\Customer\MembershipController::class, 'freeze'])->name('memberships.freeze');
        Route::post('/memberships/{membership}/cancel',        [\App\Http\Controllers\Customer\MembershipController::class, 'cancel'])->name('memberships.cancel');

        Route::get('/profile',           [\App\Http\Controllers\Customer\ProfileController::class, 'edit'])->name('profile.edit');
        Route::put('/profile',           [\App\Http\Controllers\Customer\ProfileController::class, 'update'])->name('profile.update');
        Route::put('/profile/password',  [\App\Http\Controllers\Customer\ProfileController::class, 'updatePassword'])->name('profile.password');
    });

    // ── Shared account routes (admin, staff, customer) ─────────────────────────
    Route::prefix('account')->group(function () {
        // 2FA management
        Route::get('/2fa',          [\App\Http\Controllers\Auth\TwoFactorController::class, 'show'])->name('2fa.show');
        Route::post('/2fa/confirm', [\App\Http\Controllers\Auth\TwoFactorController::class, 'confirm'])->name('2fa.confirm');
        Route::post('/2fa/disable', [\App\Http\Controllers\Auth\TwoFactorController::class, 'disable'])->name('2fa.disable');

        // Device / session management
        Route::get('/devices',              [\App\Http\Controllers\Auth\DeviceSessionController::class, 'index'])->name('devices.index');
        Route::delete('/devices/{session}', [\App\Http\Controllers\Auth\DeviceSessionController::class, 'destroy'])->name('devices.destroy');
        Route::delete('/devices/_/others',  [\App\Http\Controllers\Auth\DeviceSessionController::class, 'destroyOthers'])->name('devices.destroy-others');
    });
});
