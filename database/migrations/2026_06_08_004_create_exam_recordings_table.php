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
        Schema::create('exam_recordings', function (Blueprint $table) {
            $table->id();
            
            // الارتباطات
            $table->foreignId('proctoring_session_id')->constrained('proctoring_sessions')->onDelete('cascade');
            $table->foreignId('student_id')->constrained('users')->onDelete('cascade');
            
            // نوع التسجيل
            $table->enum('recording_type', ['video', 'screen', 'audio', 'screenshot']);
            
            // معلومات الملف
            $table->string('file_path')->nullable();
            $table->bigInteger('file_size')->nullable();
            $table->integer('file_duration_seconds')->nullable();
            $table->string('file_format', 10)->nullable();
            
            // التخزين
            $table->enum('storage_provider', ['s3', 'local', 'minio'])->default('local');
            $table->string('storage_path')->nullable();
            
            // المعالجة
            $table->enum('status', ['pending', 'uploading', 'processing', 'completed', 'archived', 'deleted'])->default('pending');
            $table->timestamp('processing_started_at')->nullable();
            $table->timestamp('processing_completed_at')->nullable();
            $table->text('processing_error')->nullable();
            
            // محتوى إضافي
            $table->text('transcription')->nullable();
            $table->string('thumbnail_url')->nullable();
            
            $table->timestamps();
            
            // الفهارس
            $table->index('proctoring_session_id');
            $table->index('student_id');
            $table->index('status');
            $table->index('recording_type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('exam_recordings');
    }
};
