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
        Schema::create('face_detection_logs', function (Blueprint $table) {
            $table->id();
            
            // الارتباطات
            $table->foreignId('proctoring_session_id')->constrained('proctoring_sessions')->onDelete('cascade');
            $table->foreignId('student_id')->constrained('users')->onDelete('cascade');
            
            // بيانات الكشف
            $table->integer('face_count')->default(0);
            $table->decimal('primary_face_confidence', 5, 2)->nullable();
            $table->boolean('secondary_face_detected')->default(false);
            $table->boolean('face_lost')->default(false);
            
            // الأدلة
            $table->string('screenshot_url')->nullable();
            
            $table->timestamp('timestamp')->useCurrent();
            
            // الفهارس
            $table->index('proctoring_session_id');
            $table->index('student_id');
            $table->index('timestamp');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('face_detection_logs');
    }
};
