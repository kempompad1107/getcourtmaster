<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('membership_plans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('slug');
            $table->text('description')->nullable();
            $table->enum('billing_cycle', ['monthly', 'quarterly', 'yearly', 'lifetime'])->default('monthly');
            $table->decimal('price', 10, 2);
            $table->integer('court_credits')->default(0);
            $table->decimal('discount_percent', 5, 2)->default(0);
            $table->json('perks')->nullable();
            $table->boolean('is_vip')->default(false);
            $table->integer('max_freeze_days')->default(30);
            $table->integer('freeze_count_per_year')->default(1);
            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0);
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['tenant_id', 'slug']);
        });

        Schema::create('memberships', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('plan_id')->constrained('membership_plans');
            $table->string('membership_number')->unique();
            $table->enum('status', ['active', 'expired', 'cancelled', 'frozen', 'pending'])->default('pending');
            $table->integer('remaining_credits')->default(0);
            $table->dateTime('starts_at');
            $table->dateTime('expires_at');
            $table->dateTime('frozen_at')->nullable();
            $table->dateTime('frozen_until')->nullable();
            $table->dateTime('cancelled_at')->nullable();
            $table->boolean('auto_renew')->default(true);
            $table->foreignId('referred_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['tenant_id', 'customer_id', 'status']);
            $table->index(['expires_at', 'status']);
        });

        Schema::create('membership_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('membership_id')->constrained()->cascadeOnDelete();
            $table->enum('type', ['purchase', 'renewal', 'credit_use', 'credit_add', 'freeze', 'cancel', 'refund'])->default('purchase');
            $table->integer('credits_change')->default(0);
            $table->decimal('amount', 10, 2)->default(0);
            $table->string('description')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('membership_transactions');
        Schema::dropIfExists('memberships');
        Schema::dropIfExists('membership_plans');
    }
};
