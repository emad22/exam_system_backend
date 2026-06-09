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
        Schema::create('exam_violations', function (Blueprint $table) {
            $table->id();
            
            // الارتباطات
            $table->foreignId('proctoring_session_id')->constrained('proctoring_sessions')->onDelete('cascade');
            $table->foreignId('student_id')->constrained('users')->onDelete('cascade');
            
            // نوع الانتهاك
            $table->enum('violation_type', [
                'multiple_faces',
                'face_not_visible',
                'face_swap',
                'tab_switched',
                'browser_opened',
                'copy_paste',
                'external_device',
                'suspicious_audio',
                'suspicious_behavior',
                'environment_change',
                'person_in_background',
                'phone_usage',
                'unusual_eye_movement'
            ]);
            
            // الخطورة والحالة
            $table->enum('severity', ['info', 'low', 'medium', 'high', 'critical'])->default('medium');
            $table->enum('status', ['detected', 'acknowledged', 'reviewed', 'resolved'])->default('detected');
            
            // الأدلة
            $table->string('screenshot_url')->nullable();
            $table->string('video_clip_url')->nullable();
            $table->json('evidence')->nullable();
            
            // الوصف
            $table->text('description')->nullable();
            $table->enum('detected_by', ['system', 'proctor_manual'])->default('system');
            
            // المراجعة
            $table->boolean('flagged_by_proctor')->default(false);
            $table->text('proctor_notes')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->onDelete('set null');
            $table->string('action_taken')->nullable();
            
            $table->timestamp('timestamp')->useCurrent();
            $table->timestamps();
            
            // الفهارس
            $table->index('proctoring_session_id');
            $table->index('student_id');
            $table->index('severity');
            $table->index('timestamp');
            $table->index('violation_type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('exam_violations');
    }
};
