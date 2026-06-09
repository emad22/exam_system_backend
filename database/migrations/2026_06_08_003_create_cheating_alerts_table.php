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
        Schema::create('cheating_alerts', function (Blueprint $table) {
            $table->id();
            
            // الارتباطات
            $table->foreignId('proctoring_session_id')->constrained('proctoring_sessions')->onDelete('cascade');
            $table->foreignId('violation_id')->nullable()->constrained('exam_violations')->onDelete('cascade');
            
            // نوع التنبيه
            $table->enum('alert_type', ['instant', 'threshold_reached', 'ai_detected'])->default('instant');
            $table->text('message');
            $table->enum('severity', ['warning', 'alert', 'critical'])->default('alert');
            
            // الإخطار
            $table->timestamp('sent_to_proctor_at')->useCurrent();
            $table->timestamp('proctor_acknowledged_at')->nullable();
            $table->foreignId('proctor_acknowledged_by')->nullable()->constrained('users')->onDelete('set null');
            
            $table->timestamps();
            
            // الفهارس
            $table->index('proctoring_session_id');
            $table->index('violation_id');
            $table->index('sent_to_proctor_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cheating_alerts');
    }
};
