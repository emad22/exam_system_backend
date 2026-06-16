<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Rename 'not_adaptive' to 'adaptive' and flip boolean values
     * so existing logic remains functionally identical.
     *
     * Previously: not_adaptive = true  → exam exits on failure (non-adaptive behavior)
     *             not_adaptive = false → exam continues through all levels (adaptive behavior)
     *
     * Now:        adaptive = true  → exam exits on failure when score < level threshold (adaptive placement)
     *             adaptive = false → exam does NOT exit on failure (non-adaptive behavior)
     */
    public function up(): void
    {
        Schema::table('students', function (Blueprint $table) {
            $table->renameColumn('not_adaptive', 'is_continue');
        });

        // Flip all boolean values: old not_adaptive=true → new adaptive=false, and vice versa
        DB::statement('UPDATE students SET is_continue = NOT is_continue');

        // Set the correct default (adaptive = true by default)
        Schema::table('students', function (Blueprint $table) {
            $table->boolean('is_continue')->default(true)->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('students', function (Blueprint $table) {
            $table->renameColumn('is_continue', 'adaptive');
        });

        // Flip values back
        DB::statement('UPDATE students SET adaptive = NOT adaptive');

        Schema::table('students', function (Blueprint $table) {
            $table->boolean('adaptive')->default(true)->change();
        });
    }
};
