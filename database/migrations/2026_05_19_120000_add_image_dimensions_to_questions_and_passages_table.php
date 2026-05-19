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
        Schema::table('questions', function (Blueprint $table) {
            $table->integer('image_width')->nullable()->after('image_path');
            $table->integer('image_height')->nullable()->after('image_width');
        });

        Schema::table('passages', function (Blueprint $table) {
            $table->integer('image_width')->nullable()->after('image_path');
            $table->integer('image_height')->nullable()->after('image_width');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('questions', function (Blueprint $table) {
            $table->dropColumn(['image_width', 'image_height']);
        });

        Schema::table('passages', function (Blueprint $table) {
            $table->dropColumn(['image_width', 'image_height']);
        });
    }
};
