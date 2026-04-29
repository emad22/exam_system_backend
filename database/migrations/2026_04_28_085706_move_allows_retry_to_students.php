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
        Schema::table('students', function (Blueprint $table) {
            $table->boolean('allows_retry')->default(false)->after('not_adaptive');
        });

        if (Schema::hasColumn('levels', 'allows_retry')) {
            Schema::table('levels', function (Blueprint $table) {
                $table->dropColumn('allows_retry');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('students', function (Blueprint $table) {
            $table->dropColumn('allows_retry');
        });

        Schema::table('levels', function (Blueprint $table) {
            $table->boolean('allows_retry')->default(false)->after('is_random');
        });
    }
};
