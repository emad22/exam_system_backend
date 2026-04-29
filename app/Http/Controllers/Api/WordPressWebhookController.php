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
      //  print "in register fun........";
        $secret = config('services.wordpress.webhook_secret');
        if ($request->header('X-WP-Webhook-Secret') !== $secret) {
            return response()->json(['message' => 'Unauthorized source'], 401);
        }

        $validated = $request->validate([
            'email' => 'required|email|unique:users,email',
            'first_name' => 'required|string',
            'last_name' => 'required|string',
            'package_id' => 'required|exists:packages,wp_package_id',
            'wp_user_id' => 'required|string',
            'phone' => 'required|string',
            'address' => 'nullable|string',
            'country' => 'nullable|string',
            'exam_category_id' => 'nullable|exists:exam_categories,id',
            'exam_type' => 'nullable|string', // Support legacy WP slug (adult/children)
        ]);

           
             
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
                if ($category) $categoryId = $category->id;
            }
            
            // Final fallback to first active category
            if (!$categoryId) {
                $categoryId = \App\Models\ExamCategory::where('is_active', true)->first()->id ?? null;
            }


            $username = strtolower($validated['first_name']) . rand(1000,9999);
           // dd("============== ".$username);
           // $password = Str::random(10);
            $password = strtolower($validated['first_name']).'@' . rand(10000,99999);
          //  dd("************** ".$password);
            $user = User::create([
               // 'name' => $validated['first_name'] . ' ' . $validated['last_name'],
                'first_name' => $validated['first_name'],
                'last_name' => $validated['last_name'],
                'email' => $validated['email'],
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
                'not_adaptive' => 0,
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
                'username' => $username,
                'temp_password' => $password, // Useful for debugging or sending welcome email
            ], 201);
        });

    }
}
