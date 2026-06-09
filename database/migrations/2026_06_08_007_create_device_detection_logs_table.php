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
        Schema::create('device_detection_logs', function (Blueprint $table) {
            $table->id();
            
            // الارتباطات
            $table->foreignId('proctoring_session_id')->constrained('proctoring_sessions')->onDelete('cascade');
            
            // بيانات الجهاز
            $table->enum('device_type', ['camera', 'phone', 'tablet', 'ear_device', 'usb_device', 'other']);
            $table->string('device_name')->nullable();
            $table->timestamp('detected_at')->useCurrent();
            $table->text('description')->nullable();
            
            // الفهارس
            $table->index('proctoring_session_id');
            $table->index('detected_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('device_detection_logs');
    }
};
