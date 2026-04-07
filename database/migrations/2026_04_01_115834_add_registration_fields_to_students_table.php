<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('students', function (Blueprint $table) {
            $table->unsignedBigInteger('package_id')->nullable()->after('parent_code');
            $table->enum('exam_type', ['adult', 'children'])->nullable()->after('package_id');
            $table->enum('registration_source', ['wordpress', 'manual', 'batch'])->default('manual')->after('exam_type');
            $table->string('wordpress_user_id')->nullable()->after('registration_source');

            $table->foreign('package_id')->references('id')->on('packages')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('students', function (Blueprint $table) {
            $table->dropForeign(['package_id']);
            $table->dropColumn(['package_id', 'exam_type', 'registration_source', 'wordpress_user_id']);
        });
    }
};
