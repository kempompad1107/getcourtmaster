<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('courts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('code')->nullable();
            $table->enum('type', ['indoor', 'outdoor', 'covered'])->default('indoor');
            $table->enum('status', ['available', 'occupied', 'reserved', 'maintenance', 'closed'])->default('available');
            $table->text('description')->nullable();
            $table->json('amenities')->nullable();
            $table->integer('capacity')->default(4);
            $table->decimal('base_hourly_rate', 10, 2)->default(0);
            $table->decimal('peak_hourly_rate', 10, 2)->nullable();
            $table->decimal('off_peak_hourly_rate', 10, 2)->nullable();
            $table->json('peak_hours')->nullable();
            $table->json('holiday_rates')->nullable();
            $table->json('operating_hours')->nullable();
            $table->integer('min_booking_minutes')->default(60);
            $table->integer('max_booking_minutes')->default(240);
            $table->integer('buffer_minutes')->default(15);
            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['tenant_id', 'branch_id', 'status']);
            $table->index(['tenant_id', 'is_active']);
        });

        Schema::create('court_pricing_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('court_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->enum('type', ['peak', 'off_peak', 'holiday', 'weekend', 'promo'])->default('peak');
            $table->json('applicable_days')->nullable(); // [0,1,2,3,4,5,6]
            $table->time('start_time')->nullable();
            $table->time('end_time')->nullable();
            $table->date('date_from')->nullable();
            $table->date('date_to')->nullable();
            $table->decimal('rate', 10, 2);
            $table->enum('rate_type', ['flat', 'multiplier', 'discount'])->default('flat');
            $table->integer('priority')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['court_id', 'type', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('court_pricing_rules');
        Schema::dropIfExists('courts');
    }
};
