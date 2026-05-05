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
        Schema::create('certificates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained()->onDelete('cascade');
            $table->foreignId('exam_attempt_id')->constrained()->onDelete('cascade');
            $table->foreignId('template_id')->constrained('certificate_templates')->onDelete('cascade');
            $table->string('certificate_number')->unique();
            $table->decimal('score', 5, 2);
            $table->date('issue_date');
            $table->string('verification_code')->unique();
            $table->string('file_path')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('certificates');
    }
};
