<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('exam_attempts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained()->onDelete('cascade');
            $table->foreignId('exam_id')->constrained()->onDelete('cascade');
            
            $table->enum('status', ['ongoing', 'completed', 'voided', 'paused'])->default('ongoing');
            
            $table->integer('overall_score')->nullable();
            
            // Progress tracking (exam_pos)
            $table->json('current_position')->nullable(); // e.g., {"skill_id": 1, "question_id": 42}
            
            // Proctoring / Metadata
            $table->string('proctor_value')->nullable();
            $table->string('ip_address')->nullable();
            
            $table->timestamp('started_at')->useCurrent();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exam_attempts');
    }
};
