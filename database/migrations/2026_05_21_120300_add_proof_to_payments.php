<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            if (!Schema::hasColumn('payments', 'proof_path')) {
                $table->string('proof_path')->nullable();
            }
            if (!Schema::hasColumn('payments', 'proof_uploaded_at')) {
                $table->timestamp('proof_uploaded_at')->nullable();
            }
            if (!Schema::hasColumn('payments', 'verified_at')) {
                $table->timestamp('verified_at')->nullable();
            }
            if (!Schema::hasColumn('payments', 'verified_by')) {
                $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            foreach (['proof_path', 'proof_uploaded_at', 'verified_at', 'verified_by'] as $col) {
                if (Schema::hasColumn('payments', $col)) $table->dropColumn($col);
            }
        });
    }
};
