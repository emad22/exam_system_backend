<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('system_requirements', function (Blueprint $blueprint) {
            $blueprint->id();
            $blueprint->string('title');
            $blueprint->text('description');
            $blueprint->string('category')->default('General');
            $blueprint->boolean('is_active')->default(true);
            $blueprint->boolean('is_mandatory')->default(true);
            $blueprint->integer('order')->default(0);
            $blueprint->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('system_requirements');
    }
};
