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
        Schema::create('activity_logs', function (Blueprint $col) {
            $col->id();
            $col->foreignId('user_id')->nullable()->constrained()->onDelete('set null');
            $col->string('action'); // created, updated, deleted, login, logout
            $col->string('model_type')->nullable();
            $col->unsignedBigInteger('model_id')->nullable();
            $col->text('description')->nullable();
            $col->json('changes')->nullable(); // {old: [], new: []}
            $col->string('ip_address', 45)->nullable();
            $col->text('user_agent')->nullable();
            $col->timestamps();

            $col->index(['model_type', 'model_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('activity_logs');
    }
};
