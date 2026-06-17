<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tournaments', function (Blueprint $table) {
            // Existing rows default to all-branches → no behavior change.
            $table->boolean('is_all_branches')->default(true)->after('tenant_id');
            $table->foreignId('branch_id')->nullable()->after('is_all_branches')
                ->constrained('branches')->nullOnDelete();
            $table->index(['tenant_id', 'branch_id']);
        });
    }

    public function down(): void
    {
        Schema::table('tournaments', function (Blueprint $table) {
            $table->dropForeign(['branch_id']);
            $table->dropIndex(['tenant_id', 'branch_id']);
            $table->dropColumn(['is_all_branches', 'branch_id']);
        });
    }
};
