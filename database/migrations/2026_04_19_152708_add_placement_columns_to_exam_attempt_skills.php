<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Adds placement tracking for not-adaptive students:
     *   placement_level → the level number where the student stopped (their "placement")
     *   placement_score → the percentage score they got on that level
     *   score          → general score column (if not already present)
     */
    public function up(): void
    {
        Schema::table('exam_attempt_skills', function (Blueprint $table) {
            // Add score if it doesn't exist yet
            if (!Schema::hasColumn('exam_attempt_skills', 'score')) {
                $table->decimal('score', 5, 2)->nullable()->after('max_level_reached');
            }

            // Placement columns for not-adaptive students
            if (!Schema::hasColumn('exam_attempt_skills', 'placement_level')) {
                $table->unsignedTinyInteger('placement_level')->nullable()->after('score')
                      ->comment('For not-adaptive: the level number this student is placed at');
            }
            if (!Schema::hasColumn('exam_attempt_skills', 'placement_score')) {
                $table->decimal('placement_score', 5, 2)->nullable()->after('placement_level')
                      ->comment('For not-adaptive: the % score achieved on the placement level');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('exam_attempt_skills', function (Blueprint $table) {
            $table->dropColumn(['placement_level', 'placement_score']);
        });
    }
};
