<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('exam_attempts', function (Blueprint $table) {
            $table->unsignedBigInteger('sanctum_token_id')->nullable()->after('ip_address');
            $table->foreign('sanctum_token_id')->references('id')->on('personal_access_tokens')->onDelete('set null');
        });

        Schema::table('proctoring_sessions', function (Blueprint $table) {
            $table->unsignedBigInteger('sanctum_token_id')->nullable()->after('student_id');
            $table->foreign('sanctum_token_id')->references('id')->on('personal_access_tokens')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::table('proctoring_sessions', function (Blueprint $table) {
            $table->dropForeign(['sanctum_token_id']);
            $table->dropColumn('sanctum_token_id');
        });

        Schema::table('exam_attempts', function (Blueprint $table) {
            $table->dropForeign(['sanctum_token_id']);
            $table->dropColumn('sanctum_token_id');
        });
    }
};
