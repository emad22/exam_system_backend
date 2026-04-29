<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // For MySQL/MariaDB, we usually need to use DB::statement for enum updates if we want to be safe,
        // or just redefine the column.
        DB::statement("ALTER TABLE questions MODIFY COLUMN type ENUM('mcq', 'true_false', 'short_answer', 'writing', 'speaking', 'upload', 'drag_drop', 'word_selection') DEFAULT 'mcq'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement("ALTER TABLE questions MODIFY COLUMN type ENUM('mcq', 'true_false', 'short_answer', 'writing', 'speaking', 'upload') DEFAULT 'mcq'");
    }
};
