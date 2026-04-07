<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Add user_id column
        Schema::table('students', function (Blueprint $table) {
            $table->unsignedBigInteger('user_id')->nullable()->after('id');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            
            // Make original fields nullable as they move to users table
            $table->string('email')->nullable()->change();
            $table->string('password')->nullable()->change();
        });

        // 2. Migrate existing students to users table
        $students = DB::table('students')->get();
        foreach ($students as $student) {
            if ($student->email) {
                // Check if user already exists
                $userId = DB::table('users')->where('email', $student->email)->value('id');
                
                if (!$userId) {
                    $userId = DB::table('users')->insertGetId([
                        'name' => ($student->first_name ?? 'Student') . ' ' . ($student->last_name ?? ''),
                        'email' => $student->email,
                        'password' => $student->password ?? Hash::make('password123'),
                        'role' => 'student',
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                } else {
                    // Update role if exists
                    DB::table('users')->where('id', $userId)->update(['role' => 'student']);
                }

                DB::table('students')->where('id', $student->id)->update(['user_id' => $userId]);
            }
        }
    }

    public function down(): void
    {
        Schema::table('students', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
            $table->dropColumn('user_id');
            $table->string('email')->nullable(false)->change();
            $table->string('password')->nullable(false)->change();
        });
    }
};
