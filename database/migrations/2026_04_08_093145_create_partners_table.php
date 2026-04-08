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
        // Schema::create('partners', function (Blueprint $table) {
        //     $table->id();
        //     $table->timestamps();
        Schema::create('partners', function (Blueprint $table) {
        $table->id();
        $table->string('partner_name');
        $table->string('fName_contact');
        $table->string('lName_contact');
        $table->string('email')->nullable();
        $table->string('phone')->nullable();
        $table->string('website')->nullable();
        $table->string('country');
        $table->string('r_date');
        $table->boolean('is_active')->default(true);
        $table->string('note');
        $table->timestamps();
    });
        // });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('partners');
    }
};
