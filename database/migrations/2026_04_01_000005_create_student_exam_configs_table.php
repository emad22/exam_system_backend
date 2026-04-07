<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('student_exam_configs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained()->onDelete('cascade');
            $table->foreignId('exam_id')->constrained()->onDelete('cascade');
            
            // Per-student overrides
            $table->boolean('want_reading')->default(true);
            $table->boolean('want_listening')->default(true);
            $table->boolean('want_grammar')->default(true);
            $table->boolean('want_writing')->default(false);
            $table->boolean('want_speaking')->default(false);
            
            $table->unique(['student_id', 'exam_id']);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('student_exam_configs');
    }
};
