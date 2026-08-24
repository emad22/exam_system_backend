<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Student;
use App\Models\User;
use App\Models\Package;
use App\Http\Requests\WordPressWebhook\SyncUserRequest;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;


class WordPressWebhookController extends Controller
{
    /**
     * Handle student registration from WordPress (Refactored for Unified Identity)
     */
    public function register(SyncUserRequest $request)
    {
        $validated = $request->validated();

        // Simple security check (Shared Secret)
        $secret = config('services.wordpress.webhook_secret');
        if ($request->header('X-WP-Webhook-Secret') !== $secret) {
            return response()->json(['message' => 'Unauthorized source'], 401);
        }


        return DB::transaction(function () use ($validated) {
            // 1. Fetch Package for auto-skill assignment (Try WP ID first, then internal ID)
            $package = Package::where('wp_package_id', $validated['package_id'])->first();

            if (!$package) {
                $package = Package::find($validated['package_id']);
            }

            $assignedSkills = $package ? ($package->skills ?? []) : [];
            $finalPackageId = $package ? $package->id : null;


            // Resolve Category
            $categoryId = $validated['exam_category_id'] ?? null;
            if (!$categoryId && !empty($validated['exam_type'])) {
                $category = \App\Models\ExamCategory::where('slug', $validated['exam_type'])->first();
                if ($category)
                    $categoryId = $category->id;
            }

            // Final fallback to first active category
            if (!$categoryId) {
                $categoryId = \App\Models\ExamCategory::where('is_active', true)->first()->id ?? null;
            }

            $base = str_replace('-', '', Str::slug($validated['first_name']));

            do {
                $username = $base . random_int(1000, 9999);
            } while (User::where('username', $username)->exists());

            $password = $base . '@' . random_int(10000, 99999);
            //  dd("************** ".$password);
            $user = User::create([
                // 'name' => $validated['first_name'] . ' ' . $validated['last_name'],
                'first_name' => $validated['first_name'],
                'last_name' => $validated['last_name'],
                'username' => $validated['username'] ?? ('wp_' . $validated['wp_user_id'] . '_' . Str::random(5)),
                'email' => $validated['email'] ?? null,
                'phone' => $validated['phone'],
                'address' => $validated['address'],
                'country' => $validated['country'],
                'last_name' => $validated['last_name'],
                'username' => $username,
                'password' => Hash::make($password),
                'role' => 'student',
            ]);

            // 3. Create Profile (Student)
            $student = Student::create([
                'user_id' => $user->id,
                'package_id' => $finalPackageId, // Resolved mapping
                'wordpress_user_id' => $validated['wp_user_id'],
                'registration_source' => 'wordpress',
                'exam_category_id' => $categoryId,
                'assigned_skills' => $assignedSkills,
                'is_continue' => false,
                'registration_date' => now(),
            ]);
            //adding ArabAcademy as partner...................................................................................................
            $arabAcademyPartner = \App\Models\Partner::where('id', 1)->first();
            if ($arabAcademyPartner) {
                if (is_null($student->partner_id)) {
                    $student->partner_id = $arabAcademyPartner->id;
                    $student->save();
                }
            } else {
                \Illuminate\Support\Facades\Log::error('Partner ArabAcademy not found.');
            }

            // Automated Exam Enrollment & Skill Filtering
            Student::assignDefaultExam($student);

            return response()->json([
                'message' => 'Student and User account created from WordPress successfully',
                'student_id' => $student->id,
                'user_id' => $user->id,
                'parent_code' => $student->parent_code,
                'assigned_skills' => $assignedSkills,
                'username' => $username,
                'temp_password' => $password, // Useful for debugging or sending welcome email
            ], 201);
        });

    }
}
