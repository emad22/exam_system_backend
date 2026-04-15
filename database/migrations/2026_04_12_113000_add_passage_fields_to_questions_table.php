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
            $blueprint->text('passage_content')->nullable()->after('content');
            $blueprint->string('passage_group_id')->nullable()->after('passage_content')->index();
            $blueprint->boolean('passage_randomize')->default(true)->after('passage_group_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('questions', function (Blueprint $blueprint) {
            $blueprint->dropColumn(['passage_content', 'passage_group_id', 'passage_randomize']);
        });
    }
};
