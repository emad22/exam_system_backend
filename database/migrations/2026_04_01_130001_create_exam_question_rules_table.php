<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('exam_question_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('exam_id')->constrained()->onDelete('cascade');
            $table->foreignId('skill_id')->constrained();
            
            $table->string('group_tag')->nullable(); // If null, picks from the entire skill pool
            $table->integer('quantity')->default(1); // How many questions to pick from this rule
            $table->boolean('randomize')->default(true);
            $table->integer('difficulty_level')->nullable();
            
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exam_question_rules');
    }
};
