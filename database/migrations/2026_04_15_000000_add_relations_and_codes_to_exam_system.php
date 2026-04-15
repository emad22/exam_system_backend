<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('packages', function (Blueprint $table) {
            if (!Schema::hasColumn('packages', 'exam_id')) {
                $table->foreignId('exam_id')->nullable()->constrained('exams')->nullOnDelete();
            }
        });

        Schema::table('skills', function (Blueprint $table) {
            if (!Schema::hasColumn('skills', 'short_code')) {
                $table->string('short_code', 10)->nullable()->unique();
            }
        });
    }

    public function down(): void
    {
        Schema::table('packages', function (Blueprint $table) {
            if (Schema::hasColumn('packages', 'exam_id')) {
                $table->dropForeign(['exam_id']);
                $table->dropColumn('exam_id');
            }
        });

        Schema::table('skills', function (Blueprint $table) {
            if (Schema::hasColumn('skills', 'short_code')) {
                $table->dropColumn('short_code');
            }
        });
    }
};
