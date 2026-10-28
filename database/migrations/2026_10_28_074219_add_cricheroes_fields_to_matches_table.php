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
        Schema::table('matches', function (Blueprint $table) {
            $table->string('tournament_name')->nullable()->after('venue');
            $table->string('match_type')->default('T20')->after('tournament_name');
            $table->unsignedSmallInteger('total_overs')->default(20)->after('match_type');
            $table->string('ball_type')->default('Tennis Ball')->after('total_overs'); // Tennis Ball, Leather Ball, Tape Ball
            $table->string('toss_decision')->nullable()->after('ball_type');
            $table->string('match_result')->nullable()->after('toss_decision');
            $table->string('player_of_match')->nullable()->after('match_result');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('matches', function (Blueprint $table) {
            $table->dropColumn([
                'tournament_name',
                'match_type',
                'total_overs',
                'ball_type',
                'toss_decision',
                'match_result',
                'player_of_match',
            ]);
        });
    }
};
