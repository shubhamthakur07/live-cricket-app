<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('teams', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('code', 10)->unique()->nullable();
            $table->timestamps();
        });

        Schema::create('matches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('team1_id')->nullable()->constrained('teams')->nullOnDelete();
            $table->foreignId('team2_id')->nullable()->constrained('teams')->nullOnDelete();
            $table->string('team1_name');
            $table->string('team2_name');
            $table->date('match_date');
            $table->string('venue')->nullable();
            $table->string('status')->default('scheduled');
            $table->string('external_match_id')->nullable()->unique();
            $table->timestamps();

            $table->index(['user_id', 'status']);
            $table->index('match_date');
        });

        Schema::create('scores', function (Blueprint $table) {
            $table->id();
            $table->foreignId('match_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('inning_number');
            $table->string('batting_team');
            $table->unsignedInteger('runs')->default(0);
            $table->unsignedInteger('wickets')->default(0);
            $table->string('overs', 20)->nullable();
            $table->string('status')->default('live')->comment('live|completed');
            $table->timestamps();

            $table->unique(['match_id', 'inning_number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('scores');
        Schema::dropIfExists('matches');
        Schema::dropIfExists('teams');
    }
};
