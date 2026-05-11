<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('system_requirements', function (Blueprint $table) {
            $table->string('test_type')->default('none')->after('description');
            $table->json('test_config')->nullable()->after('test_type');
        });
    }

    public function down(): void
    {
        Schema::table('system_requirements', function (Blueprint $table) {
            $table->dropColumn(['test_type', 'test_config']);
        });
    }
};
