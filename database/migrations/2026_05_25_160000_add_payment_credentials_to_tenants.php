<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            // Encrypted per-tenant payment gateway credentials, shape:
            //   { paymongo: { secret_key, webhook_secret, enabled, methods: [..] },
            //     stripe:   { secret, webhook_secret, enabled } }
            // The entire JSON blob is encrypted at rest via the Eloquent cast.
            $table->text('payment_credentials')->nullable()->after('settings');

            // Stable per-tenant webhook token used for routing inbound webhooks
            // (e.g. /webhooks/paymongo/{token}). Independent of the gateway secret
            // so tenants can rotate webhook secrets without changing their URL.
            $table->string('webhook_token', 64)->nullable()->unique()->after('payment_credentials');
        });
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropUnique(['webhook_token']);
            $table->dropColumn(['payment_credentials', 'webhook_token']);
        });
    }
};
