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

            $table->string('username')->nullable(); // username
            $table->string('email')->unique(); // email
            $table->string('password'); // password  

            $table->string('first_name')->nullable(); // first name
            $table->string('last_name')->nullable(); // last name
            $table->string('a_name')->nullable(); // Arabic name
            
            $table->date('birth_date')->nullable(); // birth date
            $table->string('phone')->nullable(); // phone
            $table->string('address')->nullable(); // address
            $table->string('city')->nullable(); // city
            $table->string('state')->nullable(); // us_state
            $table->string('country')->nullable(); // country
            $table->string('gender', 10)->nullable(); // gender
            $table->string('religion')->nullable(); // religion
            $table->string('occupation')->nullable(); // occupation
            
            $table->string('universty')->nullable(); // university
            $table->string('univ_year')->nullable(); // university year
            $table->string('academic_year')->nullable(); // academic year
            
            $table->string('come_from')->nullable(); // Referral
            $table->string('parent_code')->unique()->nullable(); // 🔥 الكود الخاص بولي الأمر
            
            $table->string('language_level')->nullable();
            $table->string('course_currently_in')->nullable();
            $table->integer('year_of_arabic')->nullable();
            
            $table->boolean('not_adaptive')->default(true);
            $table->integer('num_of_login')->default(0);
            
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
