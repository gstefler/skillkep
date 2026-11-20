<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('courses', function (Blueprint $table): void {
            $table->id();
            $table->string('title');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->boolean('is_published')->default(false);
            $table->boolean('sequential_modules')->default(false);
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('modules', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('course_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->unsignedInteger('order')->default(0);
            $table->foreignId('prerequisite_module_id')->nullable()->constrained('modules')->nullOnDelete();
            $table->timestamps();

            $table->index(['course_id', 'order']);
        });

        Schema::create('materials', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('module_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->string('type', 20);
            $table->string('path');
            $table->unsignedBigInteger('size')->default(0);
            $table->unsignedInteger('order')->default(0);
            // Video only (ADR-0004): transcoding state and the HLS output.
            $table->string('video_status', 20)->nullable();
            $table->string('hls_directory', 64)->nullable();
            $table->unsignedInteger('duration_seconds')->nullable();
            $table->text('video_error')->nullable();
            $table->timestamps();

            $table->index(['module_id', 'order']);
        });

        Schema::create('tests', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('module_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->unsignedInteger('time_limit_minutes')->nullable();
            $table->unsignedTinyInteger('pass_score')->default(60);
            $table->timestamps();
        });

        Schema::create('questions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('test_id')->constrained()->cascadeOnDelete();
            $table->string('type', 20);
            $table->text('prompt');
            $table->unsignedInteger('order')->default(0);
            $table->unsignedTinyInteger('points')->default(1);
            $table->text('correct_answer')->nullable();
            $table->timestamps();
        });

        Schema::create('question_options', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('question_id')->constrained()->cascadeOnDelete();
            $table->text('label');
            $table->text('match')->nullable();
            $table->boolean('is_correct')->default(false);
            $table->unsignedInteger('order')->default(0);
            $table->timestamps();
        });

        Schema::create('attempts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('test_id')->constrained()->cascadeOnDelete();
            $table->timestamp('started_at');
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('time_limit_expires_at')->nullable();
            $table->unsignedTinyInteger('score')->nullable();
            $table->boolean('is_passed')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'test_id']);
        });

        Schema::create('attempt_answers', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('attempt_id')->constrained()->cascadeOnDelete();
            $table->foreignId('question_id')->constrained()->cascadeOnDelete();
            $table->text('answer_value')->nullable();
            $table->boolean('is_correct')->nullable();
            $table->timestamps();

            $table->unique(['attempt_id', 'question_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attempt_answers');
        Schema::dropIfExists('attempts');
        Schema::dropIfExists('question_options');
        Schema::dropIfExists('questions');
        Schema::dropIfExists('tests');
        Schema::dropIfExists('materials');
        Schema::dropIfExists('modules');
        Schema::dropIfExists('courses');
    }
};
