<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Make exam_attempt_id nullable in proctoring_sessions
 * so we can create a pre-exam identity verification session
 * before the student has selected a skill / created an exam attempt.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('proctoring_sessions', function (Blueprint $table) {
            // Drop the FK constraint first, then re-add as nullable
            $table->dropForeign(['exam_attempt_id']);
            $table->unsignedBigInteger('exam_attempt_id')->nullable()->change();
            $table->foreign('exam_attempt_id')
                  ->references('id')
                  ->on('exam_attempts')
                  ->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::table('proctoring_sessions', function (Blueprint $table) {
            $table->dropForeign(['exam_attempt_id']);
            $table->unsignedBigInteger('exam_attempt_id')->nullable(false)->change();
            $table->foreign('exam_attempt_id')
                  ->references('id')
                  ->on('exam_attempts')
                  ->onDelete('cascade');
        });
    }
};
