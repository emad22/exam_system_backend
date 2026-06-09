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
        Schema::create('proctoring_sessions', function (Blueprint $table) {
            $table->id();
            
            // الارتباطات
            $table->foreignId('exam_attempt_id')->constrained('exam_attempts')->onDelete('cascade');
            $table->foreignId('student_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('proctor_id')->nullable()->constrained('users')->onDelete('set null');
            
            // الحالة
            $table->enum('status', ['pending', 'active', 'paused', 'ended', 'terminated'])->default('pending');
            
            // التحقق من الهوية
            $table->boolean('identity_verified')->default(false);
            $table->decimal('face_verification_score', 5, 2)->nullable();
            $table->timestamp('identity_verification_at')->nullable();
            
            // معلومات الاتصال
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->json('device_info')->nullable();
            $table->json('browser_info')->nullable();
            
            // التسجيل
            $table->enum('recording_status', ['not_started', 'recording', 'paused', 'completed', 'processed'])->default('not_started');
            $table->foreignId('video_recording_id')->nullable()->constrained('exam_recordings')->onDelete('set null');
            $table->foreignId('screen_recording_id')->nullable()->constrained('exam_recordings')->onDelete('set null');
            $table->foreignId('audio_recording_id')->nullable()->constrained('exam_recordings')->onDelete('set null');
            
            // الإحصائيات
            $table->integer('violations_count')->default(0);
            $table->decimal('risk_score', 5, 2)->default(0);
            $table->integer('face_detection_alerts')->default(0);
            $table->integer('tab_switch_alerts')->default(0);
            $table->integer('copy_paste_alerts')->default(0);
            $table->integer('external_device_alerts')->default(0);
            
            // الأوقات
            $table->timestamp('started_at')->nullable();
            $table->timestamp('paused_at')->nullable();
            $table->timestamp('resumed_at')->nullable();
            $table->timestamp('ended_at')->nullable();
            $table->integer('duration_seconds')->nullable();
            
            // التقرير
            $table->enum('report_status', ['pending', 'generated', 'reviewed', 'completed'])->default('pending');
            $table->foreignId('report_id')->nullable()->constrained('proctoring_reports')->onDelete('set null');
            $table->enum('final_verdict', ['pass', 'review_required', 'fail'])->nullable();
            
            $table->timestamps();
            
            // الفهارس
            $table->index('exam_attempt_id');
            $table->index('student_id');
            $table->index('proctor_id');
            $table->index('status');
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('proctoring_sessions');
    }
};
