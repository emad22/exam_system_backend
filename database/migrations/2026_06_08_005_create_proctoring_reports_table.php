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
        Schema::create('proctoring_reports', function (Blueprint $table) {
            $table->id();
            
            // الارتباطات
            $table->foreignId('proctoring_session_id')->constrained('proctoring_sessions')->onDelete('cascade');
            $table->foreignId('student_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('exam_id')->constrained('exams')->onDelete('cascade');
            
            // الحالة
            $table->enum('status', ['draft', 'reviewed', 'completed', 'approved'])->default('draft');
            
            // الملخص
            $table->json('violations_summary')->nullable();
            $table->integer('total_violations')->default(0);
            $table->integer('critical_violations')->default(0);
            $table->integer('high_violations')->default(0);
            $table->integer('medium_violations')->default(0);
            $table->integer('low_violations')->default(0);
            
            // التقييم
            $table->decimal('risk_score', 5, 2)->default(0);
            $table->enum('risk_level', ['low', 'medium', 'high', 'critical'])->default('low');
            $table->enum('final_verdict', ['pass', 'review_required', 'fail'])->nullable();
            
            // الملاحظات
            $table->text('system_notes')->nullable();
            $table->text('proctor_notes')->nullable();
            
            // التحليل
            $table->json('analysis_json')->nullable();
            $table->text('ai_insights')->nullable();
            
            // الموارد
            $table->string('report_pdf_url')->nullable();
            $table->boolean('recordings_included')->default(true);
            
            // المراجعة
            $table->timestamp('reviewed_at')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->onDelete('set null');
            
            $table->timestamp('generated_at')->useCurrent();
            $table->timestamps();
            
            // الفهارس
            $table->index('proctoring_session_id');
            $table->index('student_id');
            $table->index('exam_id');
            $table->index('status');
            $table->index('final_verdict');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('proctoring_reports');
    }
};
