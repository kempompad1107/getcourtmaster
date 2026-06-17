<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('report_presets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('report_type'); // revenue|bookings|courts|members|payments|audit|behavior
            $table->json('filters'); // {from, to, branch_id, group_by, ...}
            $table->boolean('is_shared')->default(false);
            $table->timestamps();

            $table->index(['tenant_id', 'user_id']);
            $table->index(['tenant_id', 'report_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('report_presets');
    }
};
