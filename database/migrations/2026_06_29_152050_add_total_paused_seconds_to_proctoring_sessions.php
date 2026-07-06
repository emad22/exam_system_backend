<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('proctoring_sessions', function (Blueprint $table) {
            $table->unsignedInteger('total_paused_seconds')->default(0)->after('duration_seconds');
        });
    }

    public function down(): void
    {
        Schema::table('proctoring_sessions', function (Blueprint $table) {
            $table->dropColumn('total_paused_seconds');
        });
    }
};
