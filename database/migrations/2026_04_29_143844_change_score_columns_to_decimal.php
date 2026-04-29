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
        Schema::table('exam_attempts', function (Blueprint $table) {
            $table->decimal('overall_score', 8, 2)->nullable()->change();
        });

        Schema::table('exam_attempt_skills', function (Blueprint $table) {
            $table->decimal('score', 8, 2)->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('exam_attempts', function (Blueprint $table) {
            $table->integer('overall_score')->nullable()->change();
        });

        Schema::table('exam_attempt_skills', function (Blueprint $table) {
            $table->integer('score')->nullable()->change();
        });
    }
};
