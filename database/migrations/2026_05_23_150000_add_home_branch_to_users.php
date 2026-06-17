<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('home_branch_id')
                ->nullable()
                ->after('tenant_id')
                ->constrained('branches')
                ->nullOnDelete();

            $table->index(['tenant_id', 'home_branch_id']);
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['tenant_id', 'home_branch_id']);
            $table->dropForeign(['home_branch_id']);
            $table->dropColumn('home_branch_id');
        });
    }
};
