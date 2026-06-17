<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('refund_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('booking_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained('users')->cascadeOnDelete();

            // Method describes how the original booking was paid — the refund
            // is delivered in the same form (cash at the desk).
            $table->enum('method', ['cash'])->default('cash');
            $table->decimal('amount', 10, 2);

            $table->enum('status', ['pending', 'processed', 'denied'])->default('pending');
            $table->text('reason')->nullable();          // why the booking was cancelled
            $table->text('denial_note')->nullable();     // staff note if denied
            $table->string('reference')->nullable();     // optional receipt/voucher # when processed

            $table->foreignId('processed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'status']);
            $table->index('booking_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('refund_requests');
    }
};
