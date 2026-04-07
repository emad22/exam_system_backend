<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('student_answers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('exam_attempt_id')->constrained()->onDelete('cascade');
            $table->foreignId('question_id')->constrained()->onDelete('cascade');
            
            // For MCQs
            $table->foreignId('option_id')->nullable()->constrained('question_options')->onDelete('cascade');
            
            // For Text/Writing/Speaking
            $table->text('text_answer')->nullable();
            
            $table->boolean('is_correct')->nullable();
            $table->integer('points_awarded')->default(0);
            
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('student_answers');
    }
};
