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
        Schema::table('students', function (Blueprint $table) {
            if (!Schema::hasColumn('students', 'is_demo')) {
                $table->boolean('is_demo')->default(false)->after('allows_retry');
            }
            if (!Schema::hasColumn('students', 'is_demo_proctored')) {
                $table->boolean('is_demo_proctored')->default(false)->after('is_demo');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('students', function (Blueprint $table) {
            $columnsToDrop = [];
            if (Schema::hasColumn('students', 'is_demo')) {
                $columnsToDrop[] = 'is_demo';
            }
            if (Schema::hasColumn('students', 'is_demo_proctored')) {
                $columnsToDrop[] = 'is_demo_proctored';
            }
            if (!empty($columnsToDrop)) {
                $table->dropColumn($columnsToDrop);
            }
        });
    }
};
