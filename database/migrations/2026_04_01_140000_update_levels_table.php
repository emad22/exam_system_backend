<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('levels', function (Blueprint $table) {
            $table->string('name')->after('skill_id')->nullable();
            $table->integer('pass_threshold')->default(70)->after('max_score'); // Add passing percentage for adaptive leveling
        });
    }

    public function down(): void
    {
        Schema::table('levels', function (Blueprint $table) {
            $table->dropColumn(['name', 'pass_threshold']);
        });
    }
};
