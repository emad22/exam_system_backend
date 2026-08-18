<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('rubric_criteria', function (Blueprint $table) {
            $table->id();
            $table->string('skill_type', 50)->default('writing'); // 'writing', 'speaking', etc.
            $table->string('category', 100); // e.g., 'الشكل العام والمفردات', 'القواعد', 'المضمون', 'البلاغة'
            $table->string('name', 150); // e.g., 'الطول', 'المفردات', 'الهجاء', 'الصرف: تصريف أفعال'
            $table->text('description')->nullable(); // Guidance / description
            $table->decimal('percentage', 6, 2)->default(0.00); // e.g., 5.00, 10.00, 15.00
            $table->decimal('max_points', 8, 2)->default(0.00); // e.g., 45.00, 90.00, 135.00
            $table->integer('order_index')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['skill_type', 'is_active', 'order_index']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('rubric_criteria');
    }
};
