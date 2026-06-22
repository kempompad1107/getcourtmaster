<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\PlatformSetting;
use App\Services\FileStorageService;
use App\Services\ImageProfile;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SettingsController extends Controller
{
    public function index(): View
    {
        $credentials = PlatformSetting::paymentCredentials();
        $branding    = PlatformSetting::branding();
        $backups     = $this->getBackupFiles();

        return view('super.settings.index', compact('credentials', 'branding', 'backups'));
    }

    public function downloadBackup(string $filename): StreamedResponse
    {
        $appName = config('app.name');
        $path    = "{$appName}/{$filename}";

        abort_unless(Storage::disk('local')->exists($path), 404);

        return Storage::disk('local')->download($path, $filename);
    }

    private function getBackupFiles(): array
    {
        $appName = config('app.name');
        $files   = Storage::disk('local')->files($appName);

        return collect($files)
            ->filter(fn ($f) => str_ends_with($f, '.zip'))
            ->map(function ($path) {
                $name = basename($path);
                return [
                    'name'       => $name,
                    'size'       => Storage::disk('local')->size($path),
                    'created_at' => \Carbon\Carbon::createFromTimestamp(
                        Storage::disk('local')->lastModified($path)
                    )->timezone(config('app.timezone')),
                    'download_url' => route('super.settings.backup.download', ['filename' => $name]),
                ];
            })
            ->sortByDesc('created_at')
            ->values()
            ->toArray();
    }

    /**
     * Upload / remove the platform logo and favicon. Both are optimised and
     * stored via FileStorageService (driver-agnostic) and their paths kept in
     * PlatformSetting so every layout can render them.
     */
    public function updateBranding(Request $request, FileStorageService $files): RedirectResponse
    {
        $request->validate([
            // SVG omitted on purpose: served same-origin from the public disk and not
            // re-encoded, so a crafted <script> SVG is a stored-XSS vector. Re-enable
            // only behind an SVG sanitizer (M-1).
            'logo'            => 'nullable|image|mimes:jpg,jpeg,png,webp|max:2048',
            'remove_logo'     => 'nullable|boolean',
            // Favicons are commonly .ico, which the `image` rule rejects — use
            // an explicit mimes list instead. SVG excluded for the same XSS reason.
            'favicon'         => 'nullable|file|mimes:ico,png,webp|max:512',
            'remove_favicon'  => 'nullable|boolean',
        ]);

        $branding = PlatformSetting::branding();
        $folder   = 'platform/branding';

        // Logo: upload new OR remove existing OR leave alone.
        if ($request->boolean('remove_logo') && !empty($branding['logo'])) {
            $files->deleteFile($branding['logo']);
            unset($branding['logo']);
        }
        if ($request->hasFile('logo')) {
            $branding['logo'] = $files->replaceFile(
                $request->file('logo'), $branding['logo'] ?? null, $folder
            );
        }

        // Favicon: keep it a small PNG (raster inputs re-encoded to PNG so the
        // alpha channel survives; .ico / .svg pass through the optimiser as-is).
        if ($request->boolean('remove_favicon') && !empty($branding['favicon'])) {
            $files->deleteFile($branding['favicon']);
            unset($branding['favicon']);
        }
        if ($request->hasFile('favicon')) {
            $branding['favicon'] = $files->replaceFile(
                $request->file('favicon'), $branding['favicon'] ?? null, $folder,
                imageProfile: new ImageProfile(256, 256, 90, 'png'),
            );
        }

        PlatformSetting::setBranding($branding);

        activity()->log('Platform branding updated by super admin');

        return back()->with('success', 'Platform branding updated.');
    }

    /**
     * Save the platform's own PayMongo / Stripe credentials. These are used by
     * BillingService to collect SaaS subscription dues from tenants. Empty key
     * inputs preserve the existing value so the UI can keep secrets masked.
     */
    public function updateGateways(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'paymongo_enabled'        => 'boolean',
            'paymongo_secret_key'     => 'nullable|string|max:255',
            'paymongo_webhook_secret' => 'nullable|string|max:255',
            'paymongo_methods'        => 'nullable|array',
            'paymongo_methods.*'      => 'in:gcash,paymaya,card,qrph',
            'stripe_enabled'          => 'boolean',
            'stripe_secret'           => 'nullable|string|max:255',
            'stripe_webhook_secret'   => 'nullable|string|max:255',
        ]);

        $existing = PlatformSetting::paymentCredentials();

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

        PlatformSetting::setPaymentCredentials([
            'paymongo' => $paymongo,
            'stripe'   => $stripe,
        ]);

        activity()->log('Platform payment gateway credentials updated by super admin');

        return back()->with('success', 'Platform payment settings updated.');
    }
}
