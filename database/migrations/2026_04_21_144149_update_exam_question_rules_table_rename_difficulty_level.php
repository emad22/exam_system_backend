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
        Schema::table('exam_question_rules', function (Blueprint $table) {
            $table->renameColumn('difficulty_level', 'level_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('exam_question_rules', function (Blueprint $table) {
            $table->renameColumn('level_id', 'difficulty_level');
        });
    }
};
