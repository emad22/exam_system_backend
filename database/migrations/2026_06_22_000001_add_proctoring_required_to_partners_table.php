<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add proctoring_required flag to partners.
     * When true, students under this partner must go through the
     * ProctoringInitializer flow instead of the System Requirements check.
     */
    public function up(): void
    {
        Schema::table('partners', function (Blueprint $table) {
            $table->boolean('proctoring_required')->default(false)->after('note');
        });
    }

    public function down(): void
    {
        Schema::table('partners', function (Blueprint $table) {
            $table->dropColumn('proctoring_required');
        });
    }
};
