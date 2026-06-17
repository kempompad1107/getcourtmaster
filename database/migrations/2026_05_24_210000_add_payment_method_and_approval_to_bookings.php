<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Bookings — record the payment source and cash-approval workflow.
        Schema::table('bookings', function (Blueprint $table) {
            // payment_method: wallet | court_credit | cash
            $table->string('payment_method', 20)->nullable()->after('paid_amount');
            $table->enum('approval_status', ['not_required', 'pending', 'approved', 'denied'])
                  ->default('not_required')->after('payment_method');
            $table->foreignId('approved_by')->nullable()->after('approval_status')
                  ->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable()->after('approved_by');
            $table->foreignId('denied_by')->nullable()->after('approved_at')
                  ->constrained('users')->nullOnDelete();
            $table->timestamp('denied_at')->nullable()->after('denied_by');
            $table->text('denial_note')->nullable()->after('denied_at');

            $table->index(['tenant_id', 'approval_status']);
        });

        // Allow "denied" as a booking status. MySQL ENUMs need a full rebuild.
        $driver = Schema::getConnection()->getDriverName();
        if ($driver === 'mysql') {
            DB::statement("ALTER TABLE bookings MODIFY COLUMN status ENUM(
                'pending', 'confirmed', 'active', 'completed',
                'cancelled', 'no_show', 'waitlisted', 'denied'
            ) NOT NULL DEFAULT 'pending'");
        }

        // Wallet transactions — record audit trail for manual adjustments.
        Schema::table('wallet_transactions', function (Blueprint $table) {
            $table->foreignId('processed_by')->nullable()->after('description')
                  ->constrained('users')->nullOnDelete();
            $table->string('note', 500)->nullable()->after('processed_by');

            $table->index(['tenant_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropIndex(['tenant_id', 'approval_status']);
            $table->dropConstrainedForeignId('approved_by');
            $table->dropConstrainedForeignId('denied_by');
            $table->dropColumn([
                'payment_method', 'approval_status',
                'approved_at', 'denied_at', 'denial_note',
            ]);
        });

        $driver = Schema::getConnection()->getDriverName();
        if ($driver === 'mysql') {
            DB::statement("ALTER TABLE bookings MODIFY COLUMN status ENUM(
                'pending', 'confirmed', 'active', 'completed',
                'cancelled', 'no_show', 'waitlisted'
            ) NOT NULL DEFAULT 'pending'");
        }

        Schema::table('wallet_transactions', function (Blueprint $table) {
            $table->dropIndex(['tenant_id', 'created_at']);
            $table->dropConstrainedForeignId('processed_by');
            $table->dropColumn('note');
        });
    }
};
