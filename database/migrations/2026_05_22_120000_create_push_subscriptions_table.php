<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('push_subscriptions', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->string('endpoint', 500);
            $t->string('p256dh_key');
            $t->string('auth_token');
            $t->string('content_encoding')->default('aesgcm');
            $t->string('user_agent', 500)->nullable();
            $t->timestamp('last_sent_at')->nullable();
            $t->timestamps();
            $t->unique(['user_id', 'endpoint']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('push_subscriptions');
    }
};
