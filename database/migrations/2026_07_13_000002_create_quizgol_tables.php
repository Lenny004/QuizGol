<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subjects', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('sections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('subject_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->string('grade')->nullable();
            $table->timestamps();
        });

        Schema::create('questions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('section_id')->constrained()->cascadeOnDelete();
            $table->text('prompt');
            $table->string('image_path')->nullable();
            $table->unsignedInteger('time_limit')->default(30);
            $table->unsignedInteger('points')->default(1000);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('answers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('question_id')->constrained()->cascadeOnDelete();
            $table->string('text');
            $table->string('image_path')->nullable();
            $table->boolean('is_correct')->default(false);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('rooms', function (Blueprint $table) {
            $table->id();
            $table->string('code', 8)->unique();
            $table->string('mode', 20)->default('quiz');
            $table->string('status', 20)->default('lobby');
            $table->foreignId('host_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('section_id')->constrained()->cascadeOnDelete();
            $table->foreignId('current_question_id')->nullable()->constrained('questions')->nullOnDelete();
            $table->timestamp('question_started_at')->nullable();
            $table->timestamps();
        });

        Schema::create('room_players', function (Blueprint $table) {
            $table->id();
            $table->foreignId('room_id')->constrained()->cascadeOnDelete();
            $table->string('nickname');
            $table->integer('score')->default(0);
            $table->unsignedBigInteger('team_id')->nullable();
            $table->string('session_token')->unique();
            $table->timestamps();
            $table->unique(['room_id', 'nickname']);
        });

        Schema::create('player_answers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('room_player_id')->constrained()->cascadeOnDelete();
            $table->foreignId('question_id')->constrained()->cascadeOnDelete();
            $table->foreignId('answer_id')->nullable()->constrained()->cascadeOnDelete();
            $table->boolean('is_correct')->default(false);
            $table->integer('points_awarded')->default(0);
            $table->timestamp('answered_at');
            $table->timestamps();
            $table->unique(['room_player_id', 'question_id']);
        });

        Schema::create('teams', function (Blueprint $table) {
            $table->id();
            $table->foreignId('room_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('side', 10);
            $table->integer('goals')->default(0);
            $table->timestamps();
        });

        Schema::create('matches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('room_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('home_team_id')->constrained('teams')->cascadeOnDelete();
            $table->foreignId('away_team_id')->constrained('teams')->cascadeOnDelete();
            $table->foreignId('turn_team_id')->nullable()->constrained('teams')->nullOnDelete();
            $table->string('status', 20)->default('lobby');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('matches');
        Schema::dropIfExists('teams');
        Schema::dropIfExists('player_answers');
        Schema::dropIfExists('room_players');
        Schema::dropIfExists('rooms');
        Schema::dropIfExists('answers');
        Schema::dropIfExists('questions');
        Schema::dropIfExists('sections');
        Schema::dropIfExists('subjects');
    }
};

