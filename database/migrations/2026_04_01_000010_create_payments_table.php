<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained();
            $table->foreignId('exam_id')->nullable()->constrained();
            
            $table->decimal('amount', 10, 2);
            $table->string('currency')->default('USD');
            $table->string('payment_method')->nullable();
            
            $table->enum('status', ['pending', 'completed', 'failed', 'refunded'])->default('completed');
            $table->string('transaction_id')->nullable();
            
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
