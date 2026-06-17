<?php

namespace App\Providers;

use App\Models\Booking;
use App\Models\Branch;
use App\Models\Court;
use App\Models\Membership;
use App\Models\Payment;
use App\Models\Tournament;
use App\Models\User;
use App\Observers\PaymentObserver;
use App\Policies\BookingPolicy;
use App\Policies\BranchPolicy;
use App\Policies\CourtPolicy;
use App\Policies\MembershipPolicy;
use App\Policies\TournamentPolicy;
use App\Policies\UserPolicy;
use App\Services\BookingService;
use App\Services\DashboardCache;
use App\Services\MembershipService;
use App\Services\PaymentService;
use App\Services\PosService;
use App\Services\PricingService;
use App\Services\QrCodeService;
use App\Services\ReportService;
use App\Services\WalletService;
use App\Services\Payments\GatewayManager;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Notifications\ChannelManager;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Shared Intervention Image manager for the centralised ImageOptimizer.
        // Driver is config-driven (GD everywhere; Imagick where available).
        $this->app->singleton(\Intervention\Image\ImageManager::class, function () {
            return new \Intervention\Image\ImageManager(
                config('images.driver') === 'imagick'
                    ? new \Intervention\Image\Drivers\Imagick\Driver()
                    : new \Intervention\Image\Drivers\Gd\Driver()
            );
        });
        $this->app->singleton(\App\Services\ImageOptimizer::class);

        $this->app->singleton(\App\Services\FileStorageService::class);
        $this->app->singleton(PricingService::class);
        $this->app->singleton(QrCodeService::class);
        $this->app->singleton(WalletService::class);
        $this->app->singleton(DashboardCache::class);
        $this->app->singleton(ReportService::class);

        $this->app->singleton(BookingService::class, fn ($app) => new BookingService(
            $app->make(PricingService::class),
            $app->make(QrCodeService::class),
            $app->make(WalletService::class),
            $app->make(\App\Services\AvailabilityService::class),
            $app->make(\App\Services\Promotions\PromotionRuleEngine::class),
        ));

        $this->app->singleton(PaymentService::class, fn ($app) => new PaymentService(
            $app->make(WalletService::class),
            $app->make(GatewayManager::class),
        ));

        $this->app->singleton(MembershipService::class);
        $this->app->singleton(PosService::class);

        $this->app->singleton(GatewayManager::class, function () {
            // Platform-level credentials are used ONLY by BillingService to charge
            // tenants for their SaaS subscription. Tenant-facing flows (booking,
            // POS, etc.) resolve credentials from the tenant row instead.
            //
            // The super admin can override these via Platform Settings (stored in
            // the platform_settings table). DB values win; env/config is fallback.
            $stored = $this->platformPaymentCredentials();
            $pm = $stored['paymongo'] ?? [];
            $sp = $stored['stripe']   ?? [];

            return new GatewayManager([
                'paymongo' => [
                    'secret_key'     => $pm['secret_key']     ?? config('services.paymongo.secret_key'),
                    'webhook_secret' => $pm['webhook_secret'] ?? config('services.paymongo.webhook_secret'),
                    'enabled'        => (bool) ($pm['secret_key'] ?? config('services.paymongo.secret_key')),
                ],
                'stripe' => [
                    'secret'         => $sp['secret']         ?? config('services.stripe.secret'),
                    'webhook_secret' => $sp['webhook_secret'] ?? config('services.stripe.webhook_secret'),
                    'enabled'        => (bool) ($sp['secret'] ?? config('services.stripe.secret')),
                ],
            ]);
        });
    }

    /**
     * Super-admin-managed platform gateway credentials, read defensively so the
     * container still boots when the table is absent (fresh install, mid-migrate).
     *
     * @return array{paymongo?: array, stripe?: array}
     */
    private function platformPaymentCredentials(): array
    {
        try {
            if (!\Illuminate\Support\Facades\Schema::hasTable('platform_settings')) {
                return [];
            }
            return \App\Models\PlatformSetting::paymentCredentials();
        } catch (\Throwable) {
            return [];
        }
    }

    public function boot(): void
    {
        // Use Bootstrap 5 pagination views — the UI is Bootstrap-based, so
        // the default Tailwind pagination renders huge unstyled chevrons.
        Paginator::useBootstrapFive();

        // Observers — Payment writes invalidate the per-tenant dashboard/
        // revenue cache so admins see new money on the next page load.
        Payment::observe(PaymentObserver::class);

        // Policies
        Gate::policy(Booking::class, BookingPolicy::class);
        Gate::policy(Branch::class, BranchPolicy::class);
        Gate::policy(Court::class, CourtPolicy::class);
        Gate::policy(Membership::class, MembershipPolicy::class);
        Gate::policy(Tournament::class, TournamentPolicy::class);
        Gate::policy(User::class, UserPolicy::class);

        // Super Admin gate
        Gate::define('super-admin', fn ($user) => $user->isSuperAdmin());

        // Module-level permissions derived from role
        Gate::before(fn ($user) => $user->isSuperAdmin() ? true : null);

        ResetPassword::createUrlUsing(fn ($user, $token) =>
            url('/reset-password?token=' . $token . '&email=' . $user->email)
        );

        // ── Rate limiters ──────────────────────────────────────────────────────
        RateLimiter::for('login', fn (Request $r) =>
            Limit::perMinute(5)->by(strtolower((string) $r->input('email')) . '|' . $r->ip())
        );
        RateLimiter::for('otp-request', fn (Request $r) =>
            Limit::perMinute(3)->by(strtolower((string) $r->input('email')) . '|' . $r->ip())
        );
        RateLimiter::for('password-reset', fn (Request $r) =>
            Limit::perMinute(3)->by(strtolower((string) $r->input('email')) . '|' . $r->ip())
        );
        RateLimiter::for('payment-webhook', fn (Request $r) =>
            Limit::perMinute(120)->by($r->ip())
        );
        RateLimiter::for('booking-create', fn (Request $r) =>
            Limit::perMinute(20)->by($r->user()?->id ?: $r->ip())
        );
        RateLimiter::for('tournament-register', fn (Request $r) =>
            Limit::perMinute(5)->by($r->user()?->id ?: $r->ip())
        );
        RateLimiter::for('2fa-verify', fn (Request $r) =>
            Limit::perMinute(5)->by($r->ip())
        );

        // Register custom notification channels
        $this->app->resolving(ChannelManager::class, function (ChannelManager $manager) {
            $manager->extend('sms', fn () => $this->app->make(\App\Notifications\Channels\SemaphoreSmsChannel::class));
            $manager->extend('webpush', fn () => $this->app->make(\App\Notifications\Channels\WebPushChannel::class));
        });
    }
}
