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
        Schema::table('proctoring_sessions', function (Blueprint $table) {
            // exam_attempt_id nullable عشان session ممكن تكون قبل الامتحان
           $table->unsignedBigInteger('exam_attempt_id')
                ->nullable()
                ->change();

            $table->timestamp('closed_at')->nullable()->after('ended_at');
            $table->string('close_reason')->nullable()->after('closed_at');
            $table->string('session_token')->nullable()->unique()->after('id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('proctoring_sessions', function (Blueprint $table) {
            //
            $table->unsignedBigInteger('exam_attempt_id')
                ->nullable(false)
                ->change();

            $table->dropColumn(['closed_at', 'close_reason', 'session_token']);
        });
    }
};
