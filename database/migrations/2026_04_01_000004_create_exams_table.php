<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('exams', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('description')->nullable();
            $table->enum('exam_type', ['adult', 'children']);
            
            $table->enum('timer_type', ['none', 'global', 'per_skill'])->default('none');
            $table->integer('duration')->nullable(); // Total minutes if global
            
            $table->boolean('as_demo')->default(false);
            $table->boolean('play_in_real_player')->default(false); // Legacy setting
            
            $table->integer('passing_score')->default(0);
            
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exams');
    }
};
