<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained('users')->cascadeOnDelete();
            $table->nullableMorphs('payable'); // booking, membership, pos_order, etc.
            $table->string('payment_number')->unique();
            $table->decimal('amount', 10, 2);
            $table->decimal('fee', 10, 2)->default(0);
            $table->string('currency', 3)->default('PHP');
            $table->enum('method', [
                'cash', 'gcash', 'maya', 'paymongo', 'stripe',
                'card', 'bank_transfer', 'wallet', 'qr',
            ])->default('cash');
            $table->enum('status', ['pending', 'paid', 'failed', 'refunded', 'partial', 'overdue'])->default('pending');
            $table->string('gateway_reference')->nullable();
            $table->string('gateway_checkout_url')->nullable();
            $table->json('gateway_response')->nullable();
            $table->string('receipt_number')->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('refunded_at')->nullable();
            $table->decimal('refund_amount', 10, 2)->default(0);
            $table->string('refund_reference')->nullable();
            $table->foreignId('processed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['tenant_id', 'status', 'method']);
            $table->index(['customer_id', 'status']);
            $table->index('payment_number');
        });

        Schema::create('wallet_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('reference')->unique();
            $table->enum('type', ['credit', 'debit', 'refund', 'reward'])->default('credit');
            $table->decimal('amount', 10, 2);
            $table->decimal('balance_before', 10, 2);
            $table->decimal('balance_after', 10, 2);
            $table->string('description')->nullable();
            $table->nullableMorphs('transactionable', 'wt_txn_idx');
            $table->timestamps();

            $table->index(['user_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wallet_transactions');
        Schema::dropIfExists('payments');
    }
};
