<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Student;
use App\Models\User;
use App\Models\Package;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class WordPressWebhookController extends Controller
{
    /**
     * Handle student registration from WordPress (Refactored for Unified Identity)
     */
    public function register(Request $request)
    {
        // Simple security check (Shared Secret)
        $secret = config('services.wordpress.webhook_secret');
        if ($request->header('X-WP-Webhook-Secret') !== $secret) {
            return response()->json(['message' => 'Unauthorized source'], 401);
        }

        $validated = $request->validate([
            'email' => 'required|email|unique:users,email',
            'first_name' => 'required|string',
            'last_name' => 'required|string',
            'package_id' => 'required|exists:packages,id',
            'wp_user_id' => 'required|string',
            'exam_type' => 'nullable|in:adult,children', // Optional override from WP
        ]);

        return DB::transaction(function () use ($validated) {
            // 1. Fetch Package for auto-skill assignment (Try WP ID first, then internal ID)
            $package = Package::where('wp_package_id', $validated['package_id'])->first();
            if (!$package) {
                $package = Package::find($validated['package_id']);
            }

            $assignedSkills = $package ? ($package->skills ?? []) : [];
            $finalPackageId = $package ? $package->id : null;
            $password = Str::random(10);
            $user = User::create([
                'name' => $validated['first_name'] . ' ' . $validated['last_name'],
                'first_name' => $validated['first_name'],
                'last_name' => $validated['last_name'],
                'email' => $validated['email'],
                'password' => Hash::make($password),
                'role' => 'student',
            ]);

            // 3. Create Profile (Student)
            $student = Student::create([
                'user_id' => $user->id,
                'package_id' => $finalPackageId, // Resolved mapping
                'wordpress_user_id' => $validated['wp_user_id'],
                'registration_source' => 'wordpress',
                'exam_type' => $validated['exam_type'] ?? 'adult',
                'assigned_skills' => $assignedSkills,
                'registration_date' => now(),
            ]);

            // Automated Exam Enrollment & Skill Filtering
            Student::assignDefaultExam($student);

            return response()->json([
                'message' => 'Student and User account created from WordPress successfully',
                'student_id' => $student->id,
                'user_id' => $user->id,
                'parent_code' => $student->parent_code,
                'assigned_skills' => $assignedSkills,
                'temp_password' => $password, // Useful for debugging or sending welcome email
            ], 201);
        });
    }
}
