<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\TenantSetting;
use App\Models\UserSession;
use App\Services\FileStorageService;
use App\Services\TwoFactor\TotpService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;

class SettingsController extends Controller
{
    public function __construct(private readonly FileStorageService $files) {}

    public function index(TotpService $totp)
    {
        $tenant = $this->authTenant();
        $user   = $this->authUser();

        // Lazily mint the per-tenant webhook token so the Gateways tab can
        // display the URL tenants must register in PayMongo/Stripe.
        $tenant->ensureWebhookToken();

        $settings = $tenant->settings ?? [];

        // 2FA setup state — only generate fresh secret if not enrolled yet.
        $secret = null;
        $qrUri  = null;
        if (!$user->two_factor_confirmed_at) {
            if (empty($user->two_factor_secret)) {
                $secret = $totp->generateSecret();
                $user->forceFill(['two_factor_secret' => Crypt::encryptString($secret)])->save();
            } else {
                $secret = Crypt::decryptString($user->two_factor_secret);
            }
            $qrUri = $totp->provisioningUri($secret, $user->email, config('app.name', 'CourtMaster'));
        }

        $sessions = UserSession::where('user_id', $user->id)
            ->whereNull('revoked_at')
            ->orderByDesc('last_active_at')
            ->get();

        $currentSessionId = request()->session()->getId();

        return view('admin.settings.index', compact(
            'tenant', 'settings', 'user', 'secret', 'qrUri', 'sessions', 'currentSessionId'
        ));
    }

    public function updateGeneral(Request $request)
    {
        $tenant = $this->authTenant();

        $data = $request->validate([
            'name'         => 'required|string|max:255',
            'email'        => 'required|email|max:255',
            'phone'        => 'nullable|string|max:30',
            'address'      => 'nullable|string|max:500',
            'city'         => 'nullable|string|max:100',
            'country'      => 'nullable|string|max:5',
            'timezone'     => 'required|string',
            'currency'     => 'required|string|size:3',

            // Public profile / branding
            'tagline'           => 'nullable|string|max:200',
            'about'             => 'nullable|string|max:2000',
            'rules'             => 'nullable|string|max:4000',
            'website'           => 'nullable|url|max:255',
            'facebook'          => 'nullable|url|max:255',
            'instagram'         => 'nullable|url|max:255',
            'brand_color'       => 'nullable|regex:/^#[0-9a-fA-F]{6}$/',
            // SVG omitted on purpose: it is served same-origin from the public disk
            // and is not re-encoded by the optimizer, so a crafted <script> SVG is a
            // stored-XSS vector. Re-enable only behind an SVG sanitizer (M-1).
            'logo'              => 'nullable|image|mimes:jpg,jpeg,png,webp|max:2048',
            'remove_logo'       => 'nullable|boolean',
            'hero_image'        => 'nullable|image|mimes:jpg,jpeg,png,webp|max:5120',
            'remove_hero_image' => 'nullable|boolean',
        ]);

        $brandingFolder = FileStorageService::FOLDER_TENANTS . "/{$tenant->id}/branding";

        // Logo: upload new OR remove existing OR leave alone.
        if ($request->boolean('remove_logo') && $tenant->logo) {
            $this->files->deleteFile($tenant->logo);
            $tenant->logo = null;
        }
        if ($request->hasFile('logo')) {
            $tenant->logo = $this->files->replaceFile(
                $request->file('logo'), $tenant->logo, $brandingFolder
            );
        }

        // Hero image: same lifecycle, but the path is stored in settings JSON
        // (Tenant model has no hero_image column).
        $existingHero = $tenant->settings['hero_image'] ?? null;
        $newHeroPath  = $existingHero;
        if ($request->boolean('remove_hero_image') && $existingHero) {
            $this->files->deleteFile($existingHero);
            $newHeroPath = null;
        }
        if ($request->hasFile('hero_image')) {
            $newHeroPath = $this->files->replaceFile(
                $request->file('hero_image'), $existingHero, $brandingFolder
            );
        }

        // Pull branding-only fields into the settings JSON.
        $brandingKeys = ['tagline', 'about', 'rules', 'website', 'facebook', 'instagram', 'brand_color'];
        $branding = collect($data)->only($brandingKeys)
            ->filter(fn ($v) => $v !== null && $v !== '')
            ->all();

        $merged = array_merge(
            collect($tenant->settings ?? [])->except(array_merge($brandingKeys, ['hero_image']))->all(),
            $branding
        );
        if ($newHeroPath) {
            $merged['hero_image'] = $newHeroPath;
        }
        $tenant->settings = $merged;

        // Tenant columns: drop branding-only keys before update().
        $tenant->fill(collect($data)->except(array_merge($brandingKeys, ['logo', 'remove_logo', 'hero_image', 'remove_hero_image']))->all());
        $tenant->save();

        return back()->with('success', 'General settings updated.');
    }

    public function updateBooking(Request $request)
    {
        $tenant = $this->authTenant();

        $data = $request->validate([
            'tax_rate'               => 'required|numeric|min:0|max:100',
            'grace_period_minutes'   => 'required|integer|min:0|max:60',
            'cancellation_hours'     => 'required|integer|min:0',
            'advance_booking_days'   => 'required|integer|min:1|max:365',
            'require_payment'        => 'boolean',
            'auto_confirm'           => 'boolean',
            'auto_stop_after_grace'  => 'boolean',
            'evening_start'          => 'required|date_format:H:i',
            'evening_end'            => 'required|date_format:H:i',
        ]);

        $settings = $tenant->settings ?? [];
        $tenant->update(['settings' => array_merge($settings, $data)]);

        return back()->with('success', 'Booking settings updated.');
    }

    public function updateNotifications(Request $request)
    {
        $tenant = $this->authTenant();

        $data = $request->validate([
            'notify_new_booking'       => 'boolean',
            'notify_cancellation'      => 'boolean',
            'notify_low_stock'         => 'boolean',
            'notify_membership_expiry' => 'boolean',
            'notification_email'       => 'nullable|email',
        ]);

        $settings = $tenant->settings ?? [];
        $tenant->update(['settings' => array_merge($settings, ['notifications' => $data])]);

        return back()->with('success', 'Notification settings updated.');
    }

    public function gatewaySetupGuide()
    {
        $tenant = $this->authTenant();
        return view('admin.settings.gateway-setup-guide', compact('tenant'));
    }

    /**
     * Save this tenant's own PayMongo / Stripe credentials. Funds will settle
     * directly to the tenant's merchant account — the platform never sees the
     * money. Empty keys preserve the existing value (so the UI can mask secrets).
     */
    public function updateGateways(Request $request)
    {
        $tenant = $this->authTenant();

        // SEC-02: payment-gateway credentials are owner-only. Staff who can
        // otherwise edit settings must not be able to change where money flows.
        abort_unless($this->authUser()->isBusinessOwner() || $this->authUser()->isSuperAdmin(), 403,
            'Only the business owner can change payment gateway credentials.');

        $data = $request->validate([
            'paymongo_enabled'         => 'boolean',
            'paymongo_secret_key'      => 'nullable|string|max:255',
            'paymongo_webhook_secret'  => 'nullable|string|max:255',
            'paymongo_methods'         => 'nullable|array',
            'paymongo_methods.*'       => 'in:gcash,paymaya,card,qrph',
            'stripe_enabled'           => 'boolean',
            'stripe_secret'            => 'nullable|string|max:255',
            'stripe_webhook_secret'    => 'nullable|string|max:255',
        ]);

        $existing = $tenant->payment_credentials ?? [];

        $paymongo = array_merge($existing['paymongo'] ?? [], [
            'enabled' => (bool) ($data['paymongo_enabled'] ?? false),
            'methods' => array_values(array_unique($data['paymongo_methods'] ?? [])),
        ]);
        if (!empty($data['paymongo_secret_key'])) {
            $paymongo['secret_key'] = $data['paymongo_secret_key'];
        }
        if (!empty($data['paymongo_webhook_secret'])) {
            $paymongo['webhook_secret'] = $data['paymongo_webhook_secret'];
        }

        $stripe = array_merge($existing['stripe'] ?? [], [
            'enabled' => (bool) ($data['stripe_enabled'] ?? false),
        ]);
        if (!empty($data['stripe_secret'])) {
            $stripe['secret'] = $data['stripe_secret'];
        }
        if (!empty($data['stripe_webhook_secret'])) {
            $stripe['webhook_secret'] = $data['stripe_webhook_secret'];
        }

        $tenant->payment_credentials = [
            'paymongo' => $paymongo,
            'stripe'   => $stripe,
        ];
        $tenant->save();

        // Eagerly mint the webhook token so the UI can show the URLs immediately.
        $tenant->ensureWebhookToken();

        return back()->with('success', 'Payment gateway credentials updated.');
    }
}
