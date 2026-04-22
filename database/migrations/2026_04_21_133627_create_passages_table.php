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
        Schema::create('passages', function (Blueprint $table) {
            $table->id();
            $table->enum('type', ['text', 'image', 'audio', 'video'])->default('text');
            $table->string('title')->nullable();
            $table->longText('content')->nullable(); // text passage
            $table->string('media_path')->nullable(); // audio/image/video
            $table->integer('questions_limit')->nullable();
            $table->boolean('is_random')->default(false);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('passages');
    }
};
