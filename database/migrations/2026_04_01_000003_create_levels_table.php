<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('levels', function (Blueprint $table) {
            $table->id();
            $table->foreignId('skill_id')->nullable()->constrained()->onDelete('cascade');
            $table->string('name')->nullable();
            $table->integer('pass_threshold')->default(70); // Add passing percentage for adaptive leveling
            $table->text('instructions')->nullable();
            $table->string('instructions_audio')->nullable();
            $table->integer('level_number'); // 1-9
            $table->integer('min_score');
            $table->integer('max_score');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('levels');
    }
};
