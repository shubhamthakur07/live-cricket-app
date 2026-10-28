<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('match_players', function (Blueprint $table) {
            $table->id();
            $table->foreignId('match_id')->constrained('matches')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedTinyInteger('team_number')->default(1); // 1 or 2
            $table->string('team_name');
            $table->string('player_name');
            $table->string('player_phone')->nullable()->index();
            $table->string('player_role')->default('All-Rounder');
            $table->boolean('is_captain')->default(false);
            $table->boolean('is_wicketkeeper')->default(false);

            // Batting performance in this match
            $table->unsignedSmallInteger('runs_scored')->default(0);
            $table->unsignedSmallInteger('balls_faced')->default(0);
            $table->unsignedSmallInteger('fours')->default(0);
            $table->unsignedSmallInteger('sixes')->default(0);
            $table->string('dismissal')->default('not out');

            // Bowling performance in this match
            $table->string('overs_bowled', 10)->default('0.0');
            $table->unsignedTinyInteger('maidens')->default(0);
            $table->unsignedSmallInteger('runs_conceded')->default(0);
            $table->unsignedTinyInteger('wickets_taken')->default(0);

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('match_players');
    }
};
