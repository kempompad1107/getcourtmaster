<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tournaments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('name', 150);
            $table->string('slug', 160);
            $table->text('description')->nullable();
            $table->string('cover_image')->nullable();
            $table->string('logo')->nullable();
            $table->string('venue')->nullable();
            $table->string('address')->nullable();
            $table->string('google_maps_url')->nullable();
            $table->string('organizer_name')->nullable();
            $table->string('contact_phone', 30)->nullable();
            $table->string('contact_email')->nullable();
            $table->dateTime('registration_opens_at')->nullable();
            $table->dateTime('registration_closes_at')->nullable();
            $table->dateTime('starts_at')->nullable();
            $table->dateTime('ends_at')->nullable();
            $table->unsignedInteger('max_participants')->nullable();
            $table->longText('rules')->nullable();
            $table->longText('waiver')->nullable();
            $table->decimal('entry_fee', 10, 2)->default(0);
            $table->string('currency', 3)->default('PHP');
            $table->enum('visibility', ['public', 'private'])->default('private');
            $table->enum('status', [
                'draft', 'registration_open', 'registration_closed',
                'ongoing', 'completed', 'cancelled',
            ])->default('draft');
            $table->json('settings')->nullable();
            $table->timestamp('archived_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['tenant_id', 'slug']);
            $table->index(['tenant_id', 'status']);
            $table->index(['tenant_id', 'visibility', 'status']);
            $table->index(['tenant_id', 'archived_at']);
        });

        Schema::create('tournament_divisions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('tournament_id')->constrained()->cascadeOnDelete();
            $table->string('name', 120);
            $table->text('description')->nullable();
            $table->string('skill_level', 50)->nullable();
            $table->unsignedTinyInteger('min_age')->nullable();
            $table->unsignedTinyInteger('max_age')->nullable();
            $table->enum('gender', ['men', 'women', 'mixed', 'open'])->default('open');
            $table->unsignedTinyInteger('team_size')->default(2);
            $table->unsignedSmallInteger('max_entries')->nullable();
            // Null entry fee inherits the tournament-level fee.
            $table->decimal('entry_fee', 10, 2)->nullable();
            $table->enum('bracket_format', [
                'single_elimination', 'double_elimination',
                'round_robin', 'group_stage', 'pool_play',
            ])->nullable();
            $table->enum('seeding_method', ['random', 'manual', 'rating'])->default('random');
            $table->json('bracket_settings')->nullable();
            $table->timestamp('bracket_generated_at')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['tenant_id', 'tournament_id']);
        });

        Schema::create('tournament_groups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('division_id')->constrained('tournament_divisions')->cascadeOnDelete();
            $table->string('name', 50);
            $table->unsignedTinyInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['tenant_id', 'division_id']);
        });

        Schema::create('tournament_teams', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('tournament_id')->constrained()->cascadeOnDelete();
            $table->foreignId('division_id')->constrained('tournament_divisions')->cascadeOnDelete();
            $table->string('name', 150);
            $table->unsignedSmallInteger('seed')->nullable();
            $table->foreignId('group_id')->nullable()->constrained('tournament_groups')->nullOnDelete();
            $table->enum('status', ['pending', 'confirmed', 'withdrawn', 'disqualified'])->default('pending');
            $table->enum('registered_via', ['admin', 'portal'])->default('admin');
            $table->foreignId('registered_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('withdrawn_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['tenant_id', 'tournament_id']);
            $table->index(['division_id', 'status']);
            $table->index(['division_id', 'seed']);
        });

        Schema::create('tournament_team_members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('team_id')->constrained('tournament_teams')->cascadeOnDelete();
            // Denormalized so the duplicate-registration guard can live at the DB level.
            $table->foreignId('tournament_id')->constrained()->cascadeOnDelete();
            $table->foreignId('division_id')->constrained('tournament_divisions')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->boolean('is_captain')->default(false);
            $table->string('skill_level', 50)->nullable();
            $table->decimal('rating', 4, 2)->nullable();
            $table->timestamps();

            $table->unique(['team_id', 'user_id']);
            $table->unique(['division_id', 'user_id']);
            $table->index(['tenant_id', 'tournament_id']);
        });

        Schema::create('tournament_matches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('tournament_id')->constrained()->cascadeOnDelete();
            $table->foreignId('division_id')->constrained('tournament_divisions')->cascadeOnDelete();
            $table->foreignId('group_id')->nullable()->constrained('tournament_groups')->nullOnDelete();
            $table->unsignedSmallInteger('match_number');
            $table->enum('bracket_type', [
                'main', 'winners', 'losers', 'grand_final', 'group', 'knockout',
            ])->default('main');
            $table->unsignedTinyInteger('round');
            $table->string('round_name', 40)->nullable();
            $table->unsignedSmallInteger('bracket_pos');
            $table->foreignId('team1_id')->nullable()->constrained('tournament_teams')->nullOnDelete();
            $table->foreignId('team2_id')->nullable()->constrained('tournament_teams')->nullOnDelete();
            $table->json('slot_labels')->nullable();
            $table->foreignId('winner_team_id')->nullable()->constrained('tournament_teams')->nullOnDelete();
            $table->foreignId('loser_team_id')->nullable()->constrained('tournament_teams')->nullOnDelete();
            $table->unsignedBigInteger('winner_advances_to_match_id')->nullable();
            $table->unsignedTinyInteger('winner_advances_to_slot')->nullable();
            $table->unsignedBigInteger('loser_advances_to_match_id')->nullable();
            $table->unsignedTinyInteger('loser_advances_to_slot')->nullable();
            $table->foreignId('court_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('referee_id')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('scheduled_at')->nullable();
            $table->dateTime('started_at')->nullable();
            $table->dateTime('finished_at')->nullable();
            $table->enum('status', [
                'pending', 'scheduled', 'called', 'playing',
                'finished', 'walkover', 'bye', 'cancelled',
            ])->default('pending');
            $table->json('scores')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'tournament_id']);
            $table->index(['division_id', 'bracket_type', 'round']);
            $table->index(['court_id', 'scheduled_at']);
            $table->index(['tenant_id', 'status', 'scheduled_at']);
        });

        // Self-referencing FKs must be added after the table exists.
        Schema::table('tournament_matches', function (Blueprint $table) {
            $table->foreign('winner_advances_to_match_id')
                ->references('id')->on('tournament_matches')->nullOnDelete();
            $table->foreign('loser_advances_to_match_id')
                ->references('id')->on('tournament_matches')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('tournament_matches', function (Blueprint $table) {
            $table->dropForeign(['winner_advances_to_match_id']);
            $table->dropForeign(['loser_advances_to_match_id']);
        });

        Schema::dropIfExists('tournament_matches');
        Schema::dropIfExists('tournament_team_members');
        Schema::dropIfExists('tournament_teams');
        Schema::dropIfExists('tournament_groups');
        Schema::dropIfExists('tournament_divisions');
        Schema::dropIfExists('tournaments');
    }
};
