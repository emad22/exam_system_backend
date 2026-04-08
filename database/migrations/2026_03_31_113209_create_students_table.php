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
        Schema::create('students', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->unsignedBigInteger('partner_id')->nullable();
            $table->foreign('partner_id')->references('id')->on('partners')->onDelete('cascade');
            $table->string('student_code')->nullable();                  // S_id القديم (كود داخلي)

            // ── بيانات التسجيل والإحالة ─────────────────────────────
            $table->string('come_from')->nullable();                     // S_come_from (Referral)
            $table->date('registration_date')->nullable();               // r_date
            $table->string('from_promotion')->nullable();                // from_promotion
            $table->string('student_type')->nullable();                  // S_type / type2
            
        
            $table->string('parent_code')->unique()->nullable(); // 🔥 الكود الخاص بولي الأمر
            $table->json('assigned_skills')->nullable(); // Stores which skills (e.g. [1,2,3]) a student can take
            $table->integer('year_of_arabic')->nullable();
            $table->boolean('not_adaptive')->default(true);
            $table->integer('num_of_login')->default(0);

            $table->unsignedBigInteger('package_id')->nullable();
            $table->enum('exam_type', ['adult', 'children'])->nullable();
            $table->enum('registration_source', ['wordpress', 'manual', 'batch'])->default('manual');
            $table->string('wordpress_user_id')->nullable();
            
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('students');
    }
};
