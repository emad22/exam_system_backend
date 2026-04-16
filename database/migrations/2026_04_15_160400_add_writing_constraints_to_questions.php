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
        Schema::table('questions', function (Blueprint $blueprint) {
            $blueprint->integer('min_words')->nullable()->after('points');
            $blueprint->integer('max_words')->nullable()->after('min_words');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('questions', function (Blueprint $blueprint) {
            $blueprint->dropColumn(['min_words', 'max_words']);
        });
    }
};
