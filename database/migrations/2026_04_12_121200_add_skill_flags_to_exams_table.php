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
        Schema::table('exams', function (Blueprint $table) {
            $table->boolean('default_want_reading')->default(false)->after('passing_score');
            $table->boolean('default_want_listening')->default(false)->after('default_want_reading');
            $table->boolean('default_want_grammar')->default(false)->after('default_want_listening');
            $table->boolean('default_want_writing')->default(false)->after('default_want_grammar');
            $table->boolean('default_want_speaking')->default(false)->after('default_want_writing');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('exams', function (Blueprint $table) {
            $table->dropColumn([
                'default_want_reading',
                'default_want_listening',
                'default_want_grammar',
                'default_want_writing',
                'default_want_speaking'
            ]);
        });
    }
};
