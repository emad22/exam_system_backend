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
        DB::statement("ALTER TABLE questions MODIFY COLUMN type ENUM('mcq', 'true_false', 'short_answer', 'writing', 'speaking', 'upload', 'drag_drop', 'word_selection', 'click_word', 'fill_blank', 'matching', 'ordering', 'highlight', 'listening') DEFAULT 'mcq'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement("ALTER TABLE questions MODIFY COLUMN type ENUM('mcq', 'true_false', 'short_answer', 'writing', 'speaking', 'upload', 'drag_drop', 'word_selection') DEFAULT 'mcq'");
    }
};
