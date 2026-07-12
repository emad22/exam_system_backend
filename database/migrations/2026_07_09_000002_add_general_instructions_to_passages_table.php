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
        Schema::table('passages', function (Blueprint $table) {
            if (!Schema::hasColumn('passages', 'general_instructions')) {
                $table->text('general_instructions')->nullable()->after('content');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('passages', function (Blueprint $table) {
            if (Schema::hasColumn('passages', 'general_instructions')) {
                $table->dropColumn('general_instructions');
            }
        });
    }
};
