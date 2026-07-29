<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cefr_actfl_thresholds', function (Blueprint $table) {
            $table->id();

            // 'core' | 'productive'
            $table->string('skill_group', 20)->index();

            // 'cefr' | 'actfl'
            $table->string('framework', 10)->index();

            // Minimum score to reach this level.
            // - core:       points on /900 scale  (e.g. 801)
            // - productive: percentage 0–100       (e.g. 90)
            $table->unsignedSmallInteger('min_score');

            // The label returned, e.g. "C1.2", "Advanced High"
            $table->string('level_label', 50);

            // Lower sort_order = higher priority (checked first)
            $table->unsignedTinyInteger('sort_order')->default(0);

            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['skill_group', 'framework', 'min_score']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cefr_actfl_thresholds');
    }
};
