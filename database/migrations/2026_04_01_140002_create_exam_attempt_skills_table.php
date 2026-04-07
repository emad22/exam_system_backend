<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('exam_attempt_skills', function (Blueprint $table) {
            $table->id();
            $table->foreignId('exam_attempt_id')->constrained()->onDelete('cascade');
            $table->foreignId('skill_id')->constrained();
            
            $table->integer('max_level_reached')->default(1); // 1 to 9
            $table->integer('score')->nullable(); // Total score in this skill
            $table->enum('status', ['in_progress', 'completed', 'failed', 'skipped'])->default('in_progress');
            
            $table->timestamp('started_at')->useCurrent();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exam_attempt_skills');
    }
};
