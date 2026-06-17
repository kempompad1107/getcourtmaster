<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('tenant_id')->nullable()->after('id')->constrained()->nullOnDelete();
            $table->string('phone')->nullable()->after('email');
            $table->string('avatar')->nullable();
            $table->enum('user_type', ['super_admin', 'business_owner', 'staff', 'customer'])->default('customer');
            $table->decimal('wallet_balance', 10, 2)->default(0);
            $table->string('referral_code', 10)->nullable()->unique();
            $table->foreignId('referred_by')->nullable()->constrained('users')->nullOnDelete();
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_login_at')->nullable();
            $table->string('google_id')->nullable();
            $table->string('facebook_id')->nullable();
            $table->boolean('two_factor_enabled')->default(false);
            $table->string('two_factor_secret')->nullable();
            $table->json('notification_preferences')->nullable();
            $table->json('preferences')->nullable();

            $table->index(['tenant_id', 'user_type']);
            $table->index('referral_code');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'tenant_id', 'phone', 'avatar', 'user_type', 'wallet_balance',
                'referral_code', 'referred_by', 'is_active', 'last_login_at',
                'google_id', 'facebook_id', 'two_factor_enabled', 'two_factor_secret',
                'notification_preferences', 'preferences',
            ]);
        });
    }
};
