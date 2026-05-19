<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('exam_skill', function (Blueprint $table) {
            // Max total points the admin allows for this skill in this exam
            $table->unsignedInteger('max_points')->default(0)->after('is_optional');
        });
    }

    public function down(): void
    {
        Schema::table('exam_skill', function (Blueprint $table) {
            $table->dropColumn('max_points');
        });
    }
};
