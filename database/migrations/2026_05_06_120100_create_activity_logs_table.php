<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Duplicate migration, handled by 2026_05_06_100000_create_activity_logs_table.php
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Handled by 2026_05_06_100000_create_activity_logs_table.php
    }
};
