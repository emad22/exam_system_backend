<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Add proctoring_mode column to partners table.
     * Values:
     * - 'none': No proctoring, basic system check only before exam, no live proctoring during exam.
     * - 'full': Full proctoring (Identity verification before exam + Live proctoring during exam).
     * - 'identity_only': Identity verification before exam, NO live proctoring during exam.
     */
    public function up(): void
    {
        Schema::table('partners', function (Blueprint $table) {
            $table->string('proctoring_mode')->default('none')->after('proctoring_required');
        });

        // Migrate existing proctoring_required boolean data to proctoring_mode
        DB::table('partners')->where('proctoring_required', true)->update(['proctoring_mode' => 'full']);
        DB::table('partners')->where('proctoring_required', false)->orWhereNull('proctoring_required')->update(['proctoring_mode' => 'none']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('partners', function (Blueprint $table) {
            $table->dropColumn('proctoring_mode');
        });
    }
};
