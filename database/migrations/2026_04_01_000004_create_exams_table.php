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
            $table->foreignId('language_id')->constrained();
            
            $table->boolean('is_adaptive')->default(false);
            $table->integer('adaptive_threshold')->default(60); // Percentage to pass level
            
            $table->enum('timer_type', ['none', 'global', 'per_skill'])->default('none');
            $table->integer('duration')->nullable(); // Total minutes if global
            
            $table->boolean('as_demo')->default(false);
            $table->boolean('play_in_real_player')->default(false); // Legacy setting
            
            $table->integer('passing_score')->default(0);
            
            // Skills included by default
            $table->boolean('default_want_reading')->default(true);
            $table->boolean('default_want_listening')->default(true);
            $table->boolean('default_want_grammar')->default(true);
            $table->boolean('default_want_writing')->default(false);
            $table->boolean('default_want_speaking')->default(false);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exams');
    }
};
