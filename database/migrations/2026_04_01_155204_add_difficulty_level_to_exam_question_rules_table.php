<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('exam_question_rules', function (Blueprint $table) {
            $table->integer('difficulty_level')->nullable()->after('skill_id');
        });
    }

    public function down(): void
    {
        Schema::table('exam_question_rules', function (Blueprint $table) {
            $table->dropColumn('difficulty_level');
        });
    }
};
