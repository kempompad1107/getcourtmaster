<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bookings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('court_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('booking_number')->unique();
            $table->enum('type', ['online', 'walk_in', 'phone'])->default('online');
            $table->enum('status', [
                'pending', 'confirmed', 'active', 'completed',
                'cancelled', 'no_show', 'waitlisted',
            ])->default('pending');
            $table->date('booking_date');
            $table->time('start_time');
            $table->time('end_time');
            $table->integer('duration_minutes');
            $table->decimal('base_amount', 10, 2);
            $table->decimal('addon_amount', 10, 2)->default(0);
            $table->decimal('discount_amount', 10, 2)->default(0);
            $table->decimal('tax_amount', 10, 2)->default(0);
            $table->decimal('total_amount', 10, 2);
            $table->decimal('paid_amount', 10, 2)->default(0);
            $table->string('promo_code')->nullable();
            $table->text('notes')->nullable();
            $table->string('qr_code')->nullable();
            $table->boolean('reminder_sent')->default(false);
            $table->timestamp('checked_in_at')->nullable();
            $table->timestamp('checked_out_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->string('cancellation_reason')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['tenant_id', 'court_id', 'booking_date', 'status']);
            $table->index(['customer_id', 'status']);
            $table->index(['tenant_id', 'status', 'booking_date']);
            $table->index('booking_number');
        });

        Schema::create('booking_addons', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('type')->nullable();
            $table->integer('quantity')->default(1);
            $table->decimal('unit_price', 10, 2);
            $table->decimal('total_price', 10, 2);
            $table->timestamps();
        });

        Schema::create('booking_timers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_id')->constrained()->cascadeOnDelete();
            $table->foreignId('court_id')->constrained()->cascadeOnDelete();
            $table->enum('status', ['running', 'paused', 'stopped', 'expired', 'overtime'])->default('running');
            $table->timestamp('started_at')->nullable();
            $table->timestamp('paused_at')->nullable();
            $table->timestamp('resumed_at')->nullable();
            $table->timestamp('stopped_at')->nullable();
            $table->timestamp('scheduled_end_at')->nullable();
            $table->integer('elapsed_seconds')->default(0);
            $table->integer('paused_seconds')->default(0);
            $table->integer('extension_seconds')->default(0);
            $table->integer('overtime_seconds')->default(0);
            $table->integer('grace_period_seconds')->default(300);
            $table->decimal('overtime_rate', 10, 2)->default(0);
            $table->decimal('overtime_charge', 10, 2)->default(0);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['court_id', 'status']);
        });

        Schema::create('waitlist_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('court_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained('users')->cascadeOnDelete();
            $table->date('preferred_date');
            $table->time('preferred_start_time');
            $table->time('preferred_end_time');
            $table->integer('duration_minutes');
            $table->enum('status', ['waiting', 'notified', 'booked', 'expired'])->default('waiting');
            $table->timestamp('notified_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            $table->index(['court_id', 'preferred_date', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('waitlist_entries');
        Schema::dropIfExists('booking_timers');
        Schema::dropIfExists('booking_addons');
        Schema::dropIfExists('bookings');
    }
};
