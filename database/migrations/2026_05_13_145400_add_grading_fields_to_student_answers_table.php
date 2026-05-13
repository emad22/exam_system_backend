<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('student_answers', function (Blueprint $table) {
            $table->text('teacher_feedback')->nullable()->after('points_awarded');
            $table->json('grading_details')->nullable()->after('teacher_feedback'); // For rubrics/AI notes
            $table->boolean('is_manual_graded')->default(false)->after('is_correct');
        });
    }

    public function down(): void
    {
        Schema::table('student_answers', function (Blueprint $table) {
            $table->dropColumn(['teacher_feedback', 'grading_details', 'is_manual_graded']);
        });
    }
};
