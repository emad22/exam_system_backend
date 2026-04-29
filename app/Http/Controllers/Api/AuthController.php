<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Student;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    public function register(Request $request)
    {
        $validated = $request->validate([
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'phone' => 'nullable|string|max:20',
            'birth_date' => 'nullable|date',
            'gender' => 'nullable|in:male,female,other',
            'exam_category_id' => 'required|exists:exam_categories,id',
            'password' => 'required|min:6|confirmed',
        ]);

        $user = User::create([
            'name' => $validated['first_name'] . ' ' . $validated['last_name'],
            'first_name' => $validated['first_name'],
            'last_name' => $validated['last_name'],
            'email' => $validated['email'],
            'phone' => $validated['phone'] ?? null,
            'birth_date' => $validated['birth_date'] ?? null,
            'gender' => $validated['gender'] ?? null,
            'password' => Hash::make($validated['password']),
            'role' => 'student',
        ]);

        $student = Student::create([
            'user_id' => $user->id,
            'exam_category_id' => $validated['exam_category_id'],
            'registration_source' => 'website',
            'registration_date' => now(),
        ]);

        // Automated Exam Enrollment & Skill Filtering
        Student::assignDefaultExam($student);

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'message' => 'Registered successfully',
            'token' => $token,
            'user' => $user->load('student')
        ], 201);
    }

    public function login(Request $request)
    {
        $login = $request->input('login') ?? $request->input('email');

        $user = User::where(function($query) use ($login) {
            $query->where('email', $login)
                  ->orWhere('username', $login);
        })->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            return response()->json([
                'message' => 'Invalid credentials'
            ], 401);
        }


        // ✅ تحقق من الحالة
        if (!$user->is_active) {
            return response()->json([
                'message' => 'Your account is deactivated. Please contact admin.'
            ], 403);
        }

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'token' => $token,
            'role' => $user->role,
            'user' => $user->load('student')
        ]);
    }
}