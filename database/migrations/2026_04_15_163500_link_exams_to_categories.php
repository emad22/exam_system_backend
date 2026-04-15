<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Rename duration and add is_active
        Schema::table('exams', function (Blueprint $table) {
            $table->renameColumn('duration', 'time_limit');
            $table->boolean('is_active')->default(true)->after('description');
        });

        // 2. Add category foreign keys
        Schema::table('exams', function (Blueprint $table) {
            $table->foreignId('exam_category_id')->nullable()->after('id')->constrained('exam_categories')->onDelete('set null');
        });

        Schema::table('students', function (Blueprint $table) {
            $table->foreignId('exam_category_id')->nullable()->after('id')->constrained('exam_categories')->onDelete('set null');
        });

        // 3. Populate default categories and migrate data
        $adultId = DB::table('exam_categories')->insertGetId([
            'name' => 'Adult (Professional)',
            'slug' => 'adult',
            'description' => 'Targeting individuals aged 18 and above.',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $childId = DB::table('exam_categories')->insertGetId([
            'name' => 'Children (K-12)',
            'slug' => 'children',
            'description' => 'Targeting young learners and school students.',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Migrate Exams
        DB::table('exams')->where('exam_type', 'adult')->update(['exam_category_id' => $adultId]);
        DB::table('exams')->where('exam_type', 'children')->update(['exam_category_id' => $childId]);

        // Migrate Students
        DB::table('students')->where('exam_type', 'adult')->update(['exam_category_id' => $adultId]);
        DB::table('students')->where('exam_type', 'children')->update(['exam_category_id' => $childId]);

        // 4. Drop old enum columns
        Schema::table('exams', function (Blueprint $table) {
            $table->dropColumn('exam_type');
        });

        Schema::table('students', function (Blueprint $table) {
            $table->dropColumn('exam_type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Reverse operations manually if needed, but for now we focus on forward progress.
        // Usually, reverting this would require re-adding enums and copying data back.
    }
};
