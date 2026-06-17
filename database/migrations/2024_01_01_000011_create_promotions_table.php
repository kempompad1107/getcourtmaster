<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('promotions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('code')->nullable();
            $table->text('description')->nullable();
            $table->enum('type', [
                'percentage', 'fixed', 'buy_x_get_y', 'bundle',
                'peak_discount', 'referral', 'membership',
            ])->default('percentage');
            $table->decimal('value', 10, 2); // % or fixed amount
            $table->decimal('min_spend', 10, 2)->default(0);
            $table->decimal('max_discount', 10, 2)->nullable();
            $table->integer('usage_limit')->nullable();
            $table->integer('usage_per_customer')->default(1);
            $table->integer('used_count')->default(0);
            $table->json('applicable_courts')->nullable(); // null = all courts
            $table->json('applicable_days')->nullable();
            $table->time('applicable_from_time')->nullable();
            $table->time('applicable_to_time')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['tenant_id', 'code']);
            $table->index(['tenant_id', 'is_active', 'starts_at', 'ends_at']);
        });

        Schema::create('promotion_usages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('promotion_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained('users')->cascadeOnDelete();
            $table->nullableMorphs('usable');
            $table->decimal('discount_applied', 10, 2);
            $table->timestamps();

            $table->index(['promotion_id', 'customer_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('promotion_usages');
        Schema::dropIfExists('promotions');
    }
};
