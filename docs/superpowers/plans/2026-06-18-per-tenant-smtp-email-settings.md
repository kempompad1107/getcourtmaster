# Per-tenant SMTP / Email Settings Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Let each tenant control their own email — turn business notifications on/off and optionally send through their own SMTP server — while guaranteeing a mail failure can never break a booking/cancel/refund.

**Architecture:** A `TenantMailManager` resolves which mailer a tenant's *business* notifications use (own SMTP → platform `.env` fallback → discard when disabled). A `SetTenantMailer` middleware applies that mailer as the request default for authenticated tenant users. Notification dispatch in `BookingService` moves after the DB commit and is wrapped in try/catch so mail can't roll anything back. A new **Settings → Email** tab stores encrypted per-tenant SMTP credentials (owner-only, masked) with a test-send button.

**Tech Stack:** Laravel 11, Blade + Alpine.js, MySQL (prod) / sqlite `:memory:` (tests), Bootstrap 5.

---

## Testing note (READ FIRST)

- The full suite is **broken under `RefreshDatabase`** on sqlite (a MySQL-only `MODIFY ENUM` migration fails at migrate time — see memory `test-suite-sqlite-broken`). Do **not** add `RefreshDatabase`/`DatabaseMigrations` to new tests.
- Booting `Tests\TestCase` **without** a database trait still gives you a working `config()`/encryption/container — migrations are not run. All new unit tests operate on **in-memory, unsaved** `new Tenant([...])` models, so they run cleanly.
- `phpunit.xml` sets `MAIL_MAILER=array`, so the platform default mailer in tests is `array`.
- DB-dependent behavior (the cancel-resilience regression) is verified with a **tinker snippet** against the dev DB, since the feature-test path can't boot.
- Run a single test file with: `php artisan test --filter <TestClassName>`.

---

## File Structure

- **Create** `app/Services/TenantMailManager.php` — owns mailer resolution + runtime registration for a tenant.
- **Create** `app/Http/Middleware/SetTenantMailer.php` — applies the tenant's mailer as request default.
- **Create** `database/migrations/2026_06_18_000000_add_mail_credentials_to_tenants_table.php` — encrypted credentials column.
- **Create** `app/Notifications/TestMailNotification.php` — simple message for the test-send button.
- **Create** `tests/Unit/TenantMailManagerTest.php` — DB-less resolution tests.
- **Create** `tests/Unit/TenantMailHelpersTest.php` — DB-less `Tenant` helper tests.
- **Modify** `app/Models/Tenant.php` — cast/hide `mail_credentials`; add `mailEnabled()`, `requireSmtp()`, `smtpCredentials()`, `usesOwnSmtp()`.
- **Modify** `app/Services/BookingService.php` — guard the owner/staff notify loop; dispatch after commit in `cancel()`.
- **Modify** `app/Http/Controllers/Admin/SettingsController.php` — `updateNotifications` (+`email_enabled`), new `updateEmail`, new `testMail`.
- **Modify** `routes/web.php` — routes for `settings.email` (PUT) and `settings.email.test` (POST).
- **Modify** `bootstrap/app.php` — append `SetTenantMailer` to the web middleware group.
- **Modify** `resources/views/admin/settings/index.blade.php` — add the "Email" tab + pane.

---

## Task 1: `mail_credentials` column + Tenant helpers

**Files:**
- Create: `database/migrations/2026_06_18_000000_add_mail_credentials_to_tenants_table.php`
- Modify: `app/Models/Tenant.php`
- Test: `tests/Unit/TenantMailHelpersTest.php`

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Unit;

use App\Models\Tenant;
use Tests\TestCase;

class TenantMailHelpersTest extends TestCase
{
    public function test_mail_enabled_defaults_true_and_require_smtp_defaults_false(): void
    {
        $tenant = new Tenant(['settings' => []]);

        $this->assertTrue($tenant->mailEnabled());
        $this->assertFalse($tenant->requireSmtp());
    }

    public function test_mail_enabled_and_require_smtp_read_from_settings(): void
    {
        $tenant = new Tenant(['settings' => [
            'notifications' => ['email_enabled' => false],
            'mail'          => ['require_smtp' => true],
        ]]);

        $this->assertFalse($tenant->mailEnabled());
        $this->assertTrue($tenant->requireSmtp());
    }

    public function test_smtp_credentials_returns_null_when_incomplete_and_array_when_complete(): void
    {
        $incomplete = new Tenant();
        $incomplete->mail_credentials = ['host' => 'smtp.test', 'username' => 'u']; // missing password/port
        $this->assertNull($incomplete->smtpCredentials());
        $this->assertFalse($incomplete->usesOwnSmtp());

        $complete = new Tenant(['settings' => []]);
        $complete->mail_credentials = [
            'host' => 'smtp.test', 'port' => 587, 'encryption' => 'tls',
            'username' => 'u', 'password' => 'p',
            'from_address' => 'club@test.com', 'from_name' => 'Club',
        ];
        $this->assertIsArray($complete->smtpCredentials());
        $this->assertSame('smtp.test', $complete->smtpCredentials()['host']);
        $this->assertTrue($complete->usesOwnSmtp());
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter TenantMailHelpersTest`
Expected: FAIL — `Call to undefined method App\Models\Tenant::mailEnabled()`.

- [ ] **Step 3: Write the migration**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            // Whole JSON blob is encrypted at rest (SMTP password lives inside).
            $table->text('mail_credentials')->nullable()->after('payment_credentials');
        });
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn('mail_credentials');
        });
    }
};
```

- [ ] **Step 4: Add cast, hidden, and helpers to `Tenant`**

In `app/Models/Tenant.php`, add to the `$casts` array (after the `payment_credentials` line):

```php
        'mail_credentials' => 'encrypted:array',
```

Change the `$hidden` line to include the new column:

```php
    protected $hidden = ['payment_credentials', 'mail_credentials'];
```

Add these methods near `notificationEmail()`:

```php
    /** Whether this tenant wants business email at all (default ON). */
    public function mailEnabled(): bool
    {
        return (bool) data_get($this->settings, 'notifications.email_enabled', true);
    }

    /** Whether the tenant insists on their own SMTP (warn if falling back). */
    public function requireSmtp(): bool
    {
        return (bool) data_get($this->settings, 'mail.require_smtp', false);
    }

    /**
     * Decrypted SMTP credentials, but only when complete enough to connect.
     * Returns null if any required field is missing (host/port/username/password).
     */
    public function smtpCredentials(): ?array
    {
        $c = $this->mail_credentials;
        if (!is_array($c)) {
            return null;
        }
        foreach (['host', 'port', 'username', 'password'] as $required) {
            if (empty($c[$required])) {
                return null;
            }
        }
        return $c;
    }

    /** True when this tenant will send through its OWN SMTP this request. */
    public function usesOwnSmtp(): bool
    {
        return $this->mailEnabled() && $this->smtpCredentials() !== null;
    }
```

- [ ] **Step 5: Run the migration on the dev DB**

Run: `php artisan migrate`
Expected: `... add_mail_credentials_to_tenants_table ... DONE`.

- [ ] **Step 6: Run test to verify it passes**

Run: `php artisan test --filter TenantMailHelpersTest`
Expected: PASS (3 tests).

- [ ] **Step 7: Commit**

```bash
git add database/migrations/2026_06_18_000000_add_mail_credentials_to_tenants_table.php app/Models/Tenant.php tests/Unit/TenantMailHelpersTest.php
git commit -m "feat(mail): add encrypted mail_credentials column + Tenant mail helpers"
```

---

## Task 2: `TenantMailManager` service

**Files:**
- Create: `app/Services/TenantMailManager.php`
- Test: `tests/Unit/TenantMailManagerTest.php`

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Unit;

use App\Models\Tenant;
use App\Services\TenantMailManager;
use Tests\TestCase;

class TenantMailManagerTest extends TestCase
{
    private function manager(): TenantMailManager
    {
        // Pin a known platform default regardless of phpunit env.
        config(['mail.default' => 'smtp']);
        return new TenantMailManager();
    }

    public function test_disabled_email_resolves_to_array_discard(): void
    {
        $tenant = new Tenant(['settings' => ['notifications' => ['email_enabled' => false]]]);

        $this->assertSame('array', $this->manager()->resolveMailerFor($tenant));
        $this->assertFalse($this->manager()->wouldDeliver($tenant));
    }

    public function test_no_credentials_falls_back_to_platform_default(): void
    {
        $tenant = new Tenant(['settings' => []]);

        $this->assertSame('smtp', $this->manager()->resolveMailerFor($tenant));
        $this->assertTrue($this->manager()->wouldDeliver($tenant));
    }

    public function test_complete_credentials_register_and_return_tenant_smtp(): void
    {
        $tenant = new Tenant(['settings' => []]);
        $tenant->mail_credentials = [
            'host' => 'smtp.mailtrap.io', 'port' => 587, 'encryption' => 'tls',
            'username' => 'user', 'password' => 'secret',
            'from_address' => 'club@test.com', 'from_name' => 'Test Club',
        ];

        $manager = $this->manager();
        $this->assertSame('tenant_smtp', $manager->resolveMailerFor($tenant));

        // The dynamic mailer config was registered from the credentials.
        $this->assertSame('smtp.mailtrap.io', config('mail.mailers.tenant_smtp.host'));
        $this->assertSame(587, config('mail.mailers.tenant_smtp.port'));
        $this->assertSame('club@test.com', config('mail.from.address'));
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter TenantMailManagerTest`
Expected: FAIL — `Class "App\Services\TenantMailManager" not found`.

- [ ] **Step 3: Implement the service**

Create `app/Services/TenantMailManager.php`:

```php
<?php

namespace App\Services;

use App\Models\Tenant;

/**
 * Decides which mailer a tenant's BUSINESS notifications use, and registers a
 * runtime "tenant_smtp" mailer from the tenant's own credentials when present.
 *
 * Hybrid model:
 *   - email disabled        -> "array" (discard: no send, no throw)
 *   - own SMTP configured   -> "tenant_smtp" (branded sender, isolated)
 *   - otherwise             -> the platform default mailer (.env fallback)
 *
 * Registered as a singleton so the platform default is snapshotted once, before
 * any per-request override mutates config('mail.default').
 */
class TenantMailManager
{
    private ?string $platformDefault = null;

    /** The original .env default mailer, captured before any override. */
    public function platformDefault(): string
    {
        return $this->platformDefault ??= (string) config('mail.default', 'smtp');
    }

    /** Resolve (and register, if needed) the mailer name for this tenant. */
    public function resolveMailerFor(Tenant $tenant): string
    {
        if (!$tenant->mailEnabled()) {
            return 'array';
        }

        $creds = $tenant->smtpCredentials();
        if ($creds === null) {
            return $this->platformDefault();
        }

        config([
            'mail.mailers.tenant_smtp' => [
                'transport'    => 'smtp',
                'host'         => $creds['host'],
                'port'         => (int) $creds['port'],
                'encryption'   => $creds['encryption'] ?? null,
                'username'     => $creds['username'],
                'password'     => $creds['password'],
                'timeout'      => null,
                'local_domain' => parse_url((string) config('app.url', 'http://localhost'), PHP_URL_HOST),
            ],
        ]);

        if (!empty($creds['from_address'])) {
            config([
                'mail.from.address' => $creds['from_address'],
                'mail.from.name'    => $creds['from_name'] ?? config('mail.from.name'),
            ]);
        }

        return 'tenant_smtp';
    }

    /** False only when the tenant has turned email off entirely. */
    public function wouldDeliver(Tenant $tenant): bool
    {
        return $tenant->mailEnabled();
    }

    /**
     * Apply the tenant's mailer as the default for the current runtime.
     * Snapshots the platform default first (idempotent enough for one request).
     */
    public function apply(Tenant $tenant): void
    {
        $this->platformDefault();                       // capture before overriding
        config(['mail.default' => $this->resolveMailerFor($tenant)]);
    }
}
```

- [ ] **Step 4: Register as a singleton**

In `app/Providers/AppServiceProvider.php`, inside `register()`, add:

```php
        $this->app->singleton(\App\Services\TenantMailManager::class);
```

- [ ] **Step 5: Run test to verify it passes**

Run: `php artisan test --filter TenantMailManagerTest`
Expected: PASS (3 tests).

- [ ] **Step 6: Commit**

```bash
git add app/Services/TenantMailManager.php app/Providers/AppServiceProvider.php tests/Unit/TenantMailManagerTest.php
git commit -m "feat(mail): TenantMailManager resolves tenant SMTP with .env fallback"
```

---

## Task 3: `SetTenantMailer` middleware

**Files:**
- Create: `app/Http/Middleware/SetTenantMailer.php`
- Modify: `bootstrap/app.php`

> No isolated unit test: this is request glue. It is exercised end-to-end during
> manual verification (Task 7). Keep it tiny and obviously correct.

- [ ] **Step 1: Create the middleware**

```php
<?php

namespace App\Http\Middleware;

use App\Services\TenantMailManager;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * For an authenticated tenant user, make that tenant's business mailer the
 * request default (own SMTP / .env fallback / discard). Guests and tenant-less
 * users (e.g. super-admin, pre-auth OTP) are left on the platform .env mailer,
 * so login/OTP/password-reset are never affected.
 */
class SetTenantMailer
{
    public function __construct(private readonly TenantMailManager $mail) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = Auth::user();
        if ($user && $user->tenant) {
            $this->mail->apply($user->tenant);
        }

        return $next($request);
    }
}
```

- [ ] **Step 2: Append to the web middleware group**

In `bootstrap/app.php`, extend the existing `$middleware->web(append: [...])` call so it reads:

```php
        $middleware->web(append: [
            \App\Http\Middleware\SecurityHeaders::class,
            \App\Http\Middleware\SetTenantMailer::class,
        ]);
```

- [ ] **Step 3: Verify the app still boots**

Run: `php artisan route:list --path=admin/settings`
Expected: lists the settings routes with no errors (confirms middleware class loads).

- [ ] **Step 4: Commit**

```bash
git add app/Http/Middleware/SetTenantMailer.php bootstrap/app.php
git commit -m "feat(mail): apply tenant mailer per request via SetTenantMailer middleware"
```

---

## Task 4: Make notifications non-fatal (the live 500 fix)

**Files:**
- Modify: `app/Services/BookingService.php:421-445` (`notifyOwnerStaff`)
- Modify: `app/Services/BookingService.php:582-623` (`cancel`)

- [ ] **Step 1: Guard the per-recipient send in `notifyOwnerStaff`**

Replace the recipients loop (currently `BookingService.php:434-436`):

```php
        foreach ($recipients as $r) {
            $r->notify($notification);
        }
```

with a guarded version that mirrors the existing CC try/catch right below it:

```php
        foreach ($recipients as $r) {
            try {
                $r->notify($notification);
            } catch (\Throwable $e) {
                // A broken SMTP server must never break the operation that
                // triggered the alert (e.g. a cancellation). Log and move on.
                Log::warning('owner/staff notification failed', [
                    'user_id' => $r->id,
                    'error'   => $e->getMessage(),
                ]);
            }
        }
```

(`Log` is already imported in this file — it is used by the CC catch below.)

- [ ] **Step 2: Dispatch the cancel notification AFTER the transaction commits**

In `cancel()`, the `notifyOwnerStaff(...)` call currently sits **inside** the
`DB::transaction` closure (`BookingService.php:614-619`). A mail failure there
rolls the cancel back. Move it out using `DB::afterCommit`. Replace:

```php
            event(new BookingCancelled($booking));

            // Owner/staff alert (gated by the venue's "Booking cancelled" toggle).
            $this->notifyOwnerStaff(
                $booking->tenant_id,
                new BookingCancelledNotification($booking->fresh()),
                'notify_cancellation'
            );

            return $booking;
```

with:

```php
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
```

- [ ] **Step 3: Verify resilience via tinker (DB path can't run in the suite)**

Run this against the dev DB (pick any existing confirmed booking id):

```bash
php artisan tinker --execute="
  \$id = \App\Models\Booking::where('status','confirmed')->value('id');
  config(['mail.default' => 'failtest']);
  config(['mail.mailers.failtest' => ['transport' => 'smtp', 'host' => '127.0.0.1', 'port' => 1, 'timeout' => 1]]);
  \$b = \App\Models\Booking::find(\$id);
  app(\App\Services\BookingService::class)->cancel(\$b, 'resilience check', false, true);
  echo 'status after cancel: ' . \App\Models\Booking::find(\$id)->status . PHP_EOL;
"
```

Expected: prints `status after cancel: cancelled` and **no uncaught exception** — proving a dead SMTP server no longer blocks the cancel. (A `Log::warning` about the failed send is expected.)

- [ ] **Step 4: Commit**

```bash
git add app/Services/BookingService.php
git commit -m "fix(booking): mail failures no longer roll back cancel (after-commit + try/catch)"
```

---

## Task 5: Settings controller — email toggles, SMTP creds, test-send

**Files:**
- Modify: `app/Http/Controllers/Admin/SettingsController.php:155-171` (`updateNotifications`)
- Modify: `app/Http/Controllers/Admin/SettingsController.php` (add `updateEmail`, `testMail`)
- Create: `app/Notifications/TestMailNotification.php`
- Modify: `routes/web.php` (after line 388)

- [ ] **Step 1: Create the test-send notification**

Create `app/Notifications/TestMailNotification.php`:

```php
<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** One-off message sent by the Email settings "Send test email" button. */
class TestMailNotification extends Notification
{
    public function __construct(public readonly string $tenantName) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Test email from ' . $this->tenantName)
            ->greeting('It works!')
            ->line('This is a test email from your CourtMaster email settings.')
            ->line('If you received this, your SMTP configuration is working.');
    }
}
```

- [ ] **Step 2: Extend `updateNotifications` to persist `email_enabled`**

Replace the body of `updateNotifications` (`SettingsController.php:155-171`) with:

```php
    public function updateNotifications(Request $request)
    {
        $tenant = $this->authTenant();

        $data = $request->validate([
            'email_enabled'            => 'boolean',
            'notify_new_booking'       => 'boolean',
            'notify_cancellation'      => 'boolean',
            'notify_low_stock'         => 'boolean',
            'notify_membership_expiry' => 'boolean',
            'notification_email'       => 'nullable|email',
        ]);

        $settings = $tenant->settings ?? [];
        // Preserve any keys already under notifications (e.g. future additions).
        $notifications = array_merge($settings['notifications'] ?? [], $data);
        $tenant->update(['settings' => array_merge($settings, ['notifications' => $notifications])]);

        return back()->with('success', 'Notification settings updated.');
    }
```

- [ ] **Step 3: Add `updateEmail` and `testMail` methods**

Add these methods to `SettingsController` (e.g. after `updateGateways`). Also add
`use App\Notifications\TestMailNotification;`, `use App\Services\TenantMailManager;`,
and `use Illuminate\Support\Facades\Notification;` to the imports at the top.

```php
    /**
     * Save this tenant's own SMTP server + the "require my own SMTP" toggle.
     * Owner-only (mirrors gateway credentials). An empty password preserves the
     * stored one so the UI can mask the secret.
     */
    public function updateEmail(Request $request)
    {
        $tenant = $this->authTenant();

        abort_unless($this->authUser()->isBusinessOwner() || $this->authUser()->isSuperAdmin(), 403,
            'Only the business owner can change SMTP credentials.');

        $data = $request->validate([
            'require_smtp'      => 'boolean',
            'smtp_host'         => 'nullable|string|max:255',
            'smtp_port'         => 'nullable|integer|min:1|max:65535',
            'smtp_encryption'   => 'nullable|in:tls,ssl',
            'smtp_username'     => 'nullable|string|max:255',
            'smtp_password'     => 'nullable|string|max:255',
            'smtp_from_address' => 'nullable|email|max:255',
            'smtp_from_name'    => 'nullable|string|max:255',
        ]);

        // Persist the strict toggle under settings.mail.
        $settings = $tenant->settings ?? [];
        $settings['mail'] = array_merge($settings['mail'] ?? [], [
            'require_smtp' => (bool) ($data['require_smtp'] ?? false),
        ]);
        $tenant->settings = $settings;

        // Merge credentials; empty password keeps the existing value.
        $existing = $tenant->mail_credentials ?? [];
        $creds = array_merge($existing, [
            'host'         => $data['smtp_host'] ?? null,
            'port'         => $data['smtp_port'] ?? null,
            'encryption'   => $data['smtp_encryption'] ?? null,
            'username'     => $data['smtp_username'] ?? null,
            'from_address' => $data['smtp_from_address'] ?? null,
            'from_name'    => $data['smtp_from_name'] ?? null,
        ]);
        if (!empty($data['smtp_password'])) {
            $creds['password'] = $data['smtp_password'];
        }
        // If the host was cleared, treat the whole config as removed.
        $tenant->mail_credentials = empty($creds['host']) ? null : $creds;

        $tenant->save();

        return back()->with('success', 'Email settings updated.');
    }

    /** Owner-only: send a test email through the tenant's resolved mailer. */
    public function testMail(Request $request, TenantMailManager $mail)
    {
        $tenant = $this->authTenant();
        $user   = $this->authUser();

        abort_unless($user->isBusinessOwner() || $user->isSuperAdmin(), 403);

        try {
            $mail->apply($tenant);
            Notification::route('mail', $user->email)
                ->notify(new TestMailNotification($tenant->name));
        } catch (\Throwable $e) {
            return back()->with('error', 'Test email failed: ' . $e->getMessage());
        }

        return back()->with('success', "Test email sent to {$user->email}. Check your inbox.");
    }
```

- [ ] **Step 4: Add the routes**

In `routes/web.php`, immediately after the `settings.gateways.guide` route (line 388), add:

```php
        Route::put('/settings/email',       [SettingsController::class, 'updateEmail'])->name('settings.email');
        Route::post('/settings/email/test', [SettingsController::class, 'testMail'])->name('settings.email.test');
```

- [ ] **Step 5: Verify routes register**

Run: `php artisan route:list --path=admin/settings/email`
Expected: shows `admin.settings.email` (PUT) and `admin.settings.email.test` (POST).

- [ ] **Step 6: Commit**

```bash
git add app/Http/Controllers/Admin/SettingsController.php app/Notifications/TestMailNotification.php routes/web.php
git commit -m "feat(settings): email_enabled toggle, per-tenant SMTP creds, test-send endpoint"
```

---

## Task 6: Settings → Email tab UI

**Files:**
- Modify: `resources/views/admin/settings/index.blade.php:13` (tab list)
- Modify: `resources/views/admin/settings/index.blade.php` (add pane after the notifications pane, ~line 529)

- [ ] **Step 1: Add "Email" to the tab list**

Change the tab array at line 13 to include `email` (placed after `notifications`):

```php
            @foreach(['general' => 'General', 'booking' => 'Booking', 'gateways' => 'Payments', 'notifications' => 'Notifications', 'email' => 'Email', 'security' => 'Security'] as $key => $label)
```

- [ ] **Step 2: Add the Email pane**

Insert this block immediately after the notifications pane closes (after its
`</div>` at ~line 529, before the `{{-- Security --}}` comment):

```blade
        {{-- Email / SMTP --}}
        <div x-show="tab === 'email'" x-cloak>
            @php
                $mailCfg   = $tenant->mail_credentials ?? [];
                $mailFlags = $settings['mail'] ?? [];
                $isOwner   = auth()->user()->isBusinessOwner() || auth()->user()->isSuperAdmin();
                $hasOwnSmtp = !empty($mailCfg['host']);
                $requireSmtp = (bool) ($mailFlags['require_smtp'] ?? false);
            @endphp

            @if($requireSmtp && !$hasOwnSmtp)
            <div class="alert alert-warning d-flex align-items-center gap-2 mb-4">
                <i class="bi bi-exclamation-triangle"></i>
                <div>You asked to require your own SMTP, but none is configured — email is
                using the platform mailer (or not sending). Add your SMTP details below.</div>
            </div>
            @endif

            <div class="card mb-4">
                <div class="card-header set-head">
                    <span class="set-head-icon" style="--sh:#6366f1"><i class="bi bi-envelope-gear"></i></span>
                    <div>
                        <h6 class="mb-0 fw-semibold">Email Delivery (SMTP)</h6>
                        <small class="text-muted">Send notifications through your own mail server.</small>
                    </div>
                </div>
                <div class="card-body">
                    @unless($isOwner)
                    <div class="alert alert-secondary mb-0">
                        Only the business owner can change SMTP credentials.
                    </div>
                    @else
                    <form method="POST" action="{{ route('admin.settings.email') }}">
                        @csrf @method('PUT')

                        <div class="form-check form-switch mb-4">
                            <input type="hidden" name="require_smtp" value="0">
                            <input type="checkbox" name="require_smtp" value="1" id="require_smtp"
                                   class="form-check-input" @checked($requireSmtp)>
                            <label class="form-check-label" for="require_smtp">
                                Require my own SMTP (warn me if mail uses the platform mailer)
                            </label>
                        </div>

                        <div class="row g-3">
                            <div class="col-md-8">
                                <label class="form-label">SMTP host</label>
                                <input type="text" name="smtp_host" class="form-control"
                                       value="{{ $mailCfg['host'] ?? '' }}" placeholder="smtp.gmail.com">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Port</label>
                                <input type="number" name="smtp_port" class="form-control"
                                       value="{{ $mailCfg['port'] ?? '' }}" placeholder="587">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Encryption</label>
                                <select name="smtp_encryption" class="form-select">
                                    <option value="">None</option>
                                    <option value="tls" @selected(($mailCfg['encryption'] ?? '') === 'tls')>TLS</option>
                                    <option value="ssl" @selected(($mailCfg['encryption'] ?? '') === 'ssl')>SSL</option>
                                </select>
                            </div>
                            <div class="col-md-8">
                                <label class="form-label">Username</label>
                                <input type="text" name="smtp_username" autocomplete="off" class="form-control"
                                       value="{{ $mailCfg['username'] ?? '' }}">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Password</label>
                                <input type="password" name="smtp_password" autocomplete="new-password"
                                       class="form-control" placeholder="{{ !empty($mailCfg['password']) ? '•••••••• (unchanged)' : '' }}">
                                <div class="form-text">Leave blank to keep the current password.</div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">From name</label>
                                <input type="text" name="smtp_from_name" class="form-control"
                                       value="{{ $mailCfg['from_name'] ?? $tenant->name }}">
                            </div>
                            <div class="col-md-8">
                                <label class="form-label">From address</label>
                                <input type="email" name="smtp_from_address" class="form-control"
                                       value="{{ $mailCfg['from_address'] ?? '' }}" placeholder="no-reply@yourclub.com">
                            </div>
                        </div>

                        <div class="d-flex justify-content-between align-items-center mt-4">
                            <small class="text-muted">Leave host blank to use the platform mailer.</small>
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-check-lg me-1"></i>Save Email Settings
                            </button>
                        </div>
                    </form>

                    <hr class="my-4">

                    <form method="POST" action="{{ route('admin.settings.email.test') }}"
                          class="d-flex align-items-center justify-content-between gap-3">
                        @csrf
                        <div>
                            <div class="fw-semibold">Send a test email</div>
                            <small class="text-muted">Sends to {{ auth()->user()->email }} using the settings above.</small>
                        </div>
                        <button type="submit" class="btn btn-outline-primary flex-shrink-0">
                            <i class="bi bi-send me-1"></i>Send test
                        </button>
                    </form>
                    @endunless
                </div>
            </div>
        </div>
```

- [ ] **Step 3: Build assets if needed and eyeball the tab**

Run: `php artisan view:clear`
Then load `/admin/settings?tab=email` in the browser (covered fully in Task 7).

- [ ] **Step 4: Commit**

```bash
git add resources/views/admin/settings/index.blade.php
git commit -m "feat(settings): Email tab — SMTP form (owner-only, masked) + test-send"
```

---

## Task 7: End-to-end manual verification

**Files:** none (verification only)

- [ ] **Step 1: Move the `email_enabled` toggle onto the Notifications tab**

In the notifications pane (`index.blade.php` ~line 511, just before the
"Notify me when" subhead), add a master switch so staff can disable email without
owner rights:

```blade
                    <div class="form-check form-switch mb-4">
                        <input type="hidden" name="email_enabled" value="0">
                        <input type="checkbox" name="email_enabled" value="1" id="email_enabled"
                               class="form-check-input" @checked($ns['email_enabled'] ?? true)>
                        <label class="form-check-label" for="email_enabled">
                            Send email notifications
                        </label>
                    </div>
```

Commit:

```bash
git add resources/views/admin/settings/index.blade.php
git commit -m "feat(settings): master 'send email notifications' switch on Notifications tab"
```

- [ ] **Step 2: Start the app**

Run: `php artisan serve` (and `npm run dev` if assets changed). Log in as a
business owner.

- [ ] **Step 3: Verify the Email tab**

- Go to `/admin/settings?tab=email`.
- Confirm the SMTP form renders for the owner.
- Save a set of (e.g. Mailtrap) SMTP credentials. Reload — values persist, password
  shows the "unchanged" placeholder (not the raw secret).
- Click **Send test** — with valid creds you get a success flash and the message in
  the inbox; with bad creds you get the error flash showing the SMTP error string
  (and **no** 500).

- [ ] **Step 4: Verify encryption at rest**

Run: `php artisan tinker --execute="echo \App\Models\Tenant::whereNotNull('mail_credentials')->value('mail_credentials');"`
Expected: an **encrypted (unreadable) blob**, not plaintext host/password.

- [ ] **Step 5: Verify the toggle + fallback**

- On the Notifications tab, untick **Send email notifications**, save. Trigger a
  booking/cancel — no email is sent, but the in-app notification bell still updates.
- Re-tick it, clear the SMTP host on the Email tab, save. Trigger a cancel — it
  succeeds and (if `.env` SMTP works) email goes via the platform mailer.

- [ ] **Step 6: Confirm staff restriction**

Log in as a non-owner staff member. The Email tab shows the "Only the business
owner can change SMTP credentials" notice instead of the form, but the **Send email
notifications** switch on the Notifications tab is still editable.

- [ ] **Step 7: Final commit (if any view tweaks were needed)**

```bash
git add -A && git commit -m "chore(settings): verification tweaks for email tab" || echo "nothing to commit"
```

---

## Self-review checklist (completed during planning)

- **Spec coverage:** after-commit + try/catch (Task 4) ✓; TenantMailManager hybrid resolution (Task 2) ✓; middleware (Task 3) ✓; encrypted `mail_credentials` + helpers (Task 1) ✓; Email tab owner-only masked + test-send (Tasks 5–6) ✓; `email_enabled` master toggle + `require_smtp` strict warning (Tasks 5–7) ✓; auth/OTP untouched (middleware skips guests/tenant-less) ✓.
- **Placeholder scan:** every code step contains complete code; no TBD/TODO.
- **Type consistency:** `mailEnabled()`, `requireSmtp()`, `smtpCredentials()`, `usesOwnSmtp()`, `resolveMailerFor()`, `wouldDeliver()`, `apply()`, `platformDefault()` used identically across Tasks 1–5.
- **Known limitation (documented):** DB-backed feature tests can't boot on sqlite; resilience is verified via tinker (Task 4 Step 3) and manual run (Task 7).
