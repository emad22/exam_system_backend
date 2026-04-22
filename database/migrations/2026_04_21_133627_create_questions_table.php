<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('questions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('skill_id')->constrained();
            $table->foreignId('exam_id')->nullable()->constrained();
            $table->foreignId('level_id')->nullable()->constrained();
            $table->foreignId('passage_id')->nullable()->constrained()->nullOnDelete();
            $table->enum('type', ['mcq', 'true_false', 'short_answer', 'writing', 'speaking', 'upload'])->default('mcq');
            $table->text('instructions')->nullable();
            $table->text('content'); // question text
            $table->string('media_path')->nullable(); // image/audio/video
            $table->integer('min_words')->nullable();
            $table->integer('max_words')->nullable();
            $table->integer('points')->default(1);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('questions');
    }
};
