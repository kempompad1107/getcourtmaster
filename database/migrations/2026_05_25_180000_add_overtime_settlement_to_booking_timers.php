<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('booking_timers', function (Blueprint $table) {
            // 'none' = no overtime accrued (or auto-stop closed at grace).
            // 'paid' = staff collected the overtime fee at the desk.
            // 'voided' = staff waived the fee; overtime_seconds is kept for reporting.
            $table->enum('overtime_settlement', ['none', 'paid', 'voided'])
                ->default('none')
                ->after('overtime_charge');
            $table->timestamp('overtime_settled_at')->nullable()->after('overtime_settlement');
            $table->foreignId('overtime_settled_by')->nullable()->after('overtime_settled_at')->constrained('users')->nullOnDelete();
            // Snapshot of the per-tier segments at settlement time, so reports
            // can show "X min base @ ₱A + Y min peak @ ₱B" without recomputing.
            $table->json('overtime_breakdown')->nullable()->after('overtime_settled_by');
        });
    }

    public function down(): void
    {
        Schema::table('booking_timers', function (Blueprint $table) {
            $table->dropConstrainedForeignId('overtime_settled_by');
            $table->dropColumn(['overtime_settlement', 'overtime_settled_at', 'overtime_breakdown']);
        });
    }
};
