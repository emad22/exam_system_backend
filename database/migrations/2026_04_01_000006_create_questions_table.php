<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('questions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('skill_id')->constrained();
            $table->string('group_tag')->nullable()->index();
            $table->enum('type', ['mcq', 'true_false', 'short_answer', 'writing', 'speaking'])->default('mcq');
            $table->text('content'); // The question stem
            $table->string('media_path')->nullable(); // Audio/Image for the question
            $table->integer('difficulty_level')->default(1); // 1-9 level mapping
            $table->integer('points')->default(1);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('questions');
    }
};
