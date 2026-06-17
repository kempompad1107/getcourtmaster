<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Remove the vestigial off-peak / daylight pricing tier. The court form only
 * exposes Daylight (base) / Evening (peak) / Weekend rates, so off_peak_hourly_rate
 * was unmanageable and the daylight window only fed that dead branch. Daytime now
 * simply resolves to the base ("Daylight") rate.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('courts', 'off_peak_hourly_rate')) {
            Schema::table('courts', function (Blueprint $table) {
                $table->dropColumn('off_peak_hourly_rate');
            });
        }

        // Strip the now-unused daylight_start / daylight_end keys from each
        // tenant's settings JSON so nothing stale lingers.
        foreach (DB::table('tenants')->select('id', 'settings')->cursor() as $tenant) {
            if (blank($tenant->settings)) {
                continue;
            }
            $settings = json_decode($tenant->settings, true);
            if (! is_array($settings)) {
                continue;
            }
            if (array_key_exists('daylight_start', $settings) || array_key_exists('daylight_end', $settings)) {
                unset($settings['daylight_start'], $settings['daylight_end']);
                DB::table('tenants')->where('id', $tenant->id)->update(['settings' => json_encode($settings)]);
            }
        }
    }

    public function down(): void
    {
        if (! Schema::hasColumn('courts', 'off_peak_hourly_rate')) {
            Schema::table('courts', function (Blueprint $table) {
                $table->decimal('off_peak_hourly_rate', 10, 2)->nullable()->after('peak_hourly_rate');
            });
        }
    }
};
