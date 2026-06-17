<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('booking_timers', function (Blueprint $table) {
            $table->timestamp('ending_soon_alert_acknowledged_at')->nullable()->after('grace_period_seconds');
            $table->foreignId('ending_soon_alert_acknowledged_by')->nullable()->after('ending_soon_alert_acknowledged_at')->constrained('users')->nullOnDelete();
            $table->timestamp('overtime_alert_acknowledged_at')->nullable()->after('ending_soon_alert_acknowledged_by');
            $table->foreignId('overtime_alert_acknowledged_by')->nullable()->after('overtime_alert_acknowledged_at')->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('booking_timers', function (Blueprint $table) {
            $table->dropConstrainedForeignId('ending_soon_alert_acknowledged_by');
            $table->dropConstrainedForeignId('overtime_alert_acknowledged_by');
            $table->dropColumn(['ending_soon_alert_acknowledged_at', 'overtime_alert_acknowledged_at']);
        });
    }
};
