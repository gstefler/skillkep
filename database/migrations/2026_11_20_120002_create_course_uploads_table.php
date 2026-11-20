<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('course_uploads', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('fingerprint', 64);
            $table->string('filename');
            $table->unsignedBigInteger('size');
            $table->unsignedInteger('chunk_size');
            $table->unsignedInteger('total_chunks');
            $table->unsignedTinyInteger('pass_score');
            $table->boolean('lock_modules')->default(false);
            $table->string('status', 20)->default('uploading');
            $table->string('stage', 20)->nullable();
            $table->unsignedTinyInteger('stage_progress')->default(0);
            $table->foreignId('course_id')->nullable()->constrained()->nullOnDelete();
            $table->json('result')->nullable();
            $table->text('error')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'fingerprint', 'status']);
            $table->index(['status', 'updated_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('course_uploads');
    }
};
