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
        Schema::create('match_posts', function (Blueprint $table) {
            $table->id();
            $table->string('match_id')->index();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('user_name')->default('Cricket Fan');
            $table->string('avatar_color')->default('indigo');
            $table->text('content');
            $table->string('post_type')->default('text'); // 'text', 'meme', 'instagram', 'highlight'
            $table->string('media_url')->nullable();
            $table->string('insta_url')->nullable();
            $table->string('team_tag')->nullable();
            $table->unsignedInteger('likes_count')->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('match_posts');
    }
};
