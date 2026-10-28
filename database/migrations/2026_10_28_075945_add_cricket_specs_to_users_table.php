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
        Schema::table('users', function (Blueprint $table) {
            $table->string('player_role')->default('All-Rounder')->after('favorite_team');
            $table->string('batting_style')->default('Right Handed Bat')->after('player_role');
            $table->string('bowling_style')->default('Right Arm Medium')->after('batting_style');

            // Career Stats (CricHeroes Passport)
            $table->unsignedInteger('matches_played')->default(0)->after('bowling_style');
            $table->unsignedInteger('total_runs')->default(0)->after('matches_played');
            $table->string('highest_score', 10)->default('0')->after('total_runs');
            $table->decimal('batting_avg', 5, 2)->default(0.00)->after('highest_score');
            $table->decimal('strike_rate', 6, 2)->default(0.00)->after('batting_avg');
            $table->unsignedSmallInteger('fifties')->default(0)->after('strike_rate');
            $table->unsignedSmallInteger('hundreds')->default(0)->after('fifties');

            $table->unsignedInteger('wickets_taken')->default(0)->after('hundreds');
            $table->string('best_bowling', 10)->default('0/0')->after('wickets_taken');
            $table->decimal('economy_rate', 5, 2)->default(0.00)->after('best_bowling');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'player_role',
                'batting_style',
                'bowling_style',
                'matches_played',
                'total_runs',
                'highest_score',
                'batting_avg',
                'strike_rate',
                'fifties',
                'hundreds',
                'wickets_taken',
                'best_bowling',
                'economy_rate',
            ]);
        });
    }
};
