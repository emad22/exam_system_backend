<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class StaffController extends Controller
{
    /**
     * Get all staff members (Admin, Teacher, Supervisor)
     */
    public function index(Request $request)
    {
        $staff = User::where('role', '!=', 'student')
            ->orderBy('role')
            ->paginate(50);
        return response()->json($staff);
    }

    /**
     * Get a specific staff member
     */
    public function show(User $user)
    {
        if ($user->role === 'student') {
            return response()->json(['error' => 'Not a staff member.'], 422);
        }
        return response()->json($user);
    }

    /**
     * Provision a new staff identity
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:6',
            'role' => 'required|in:admin,teacher,supervisor',
            'is_active' => 'sometimes|boolean'
        ]);

        $staff = User::create([
            'first_name' => $validated['first_name'],
            'last_name' => $validated['last_name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'role' => $validated['role'],
            'is_active' => $validated['is_active'] ?? true,
        ]);

        return response()->json([
            'message' => 'Staff identity provisioned successfully.',
            'staff' => $staff
        ], 201);
    }

    /**
     * Update an existing staff role or identity
     */
    public function update(Request $request, User $user)
    {
        if ($user->role === 'student') {
            return response()->json(['error' => 'Use student identity management for this account.'], 422);
        }

        $validated = $request->validate([
            'first_name' => 'sometimes|required|string|max:255',
            'last_name' => 'sometimes|required|string|max:255',
            'email' => 'sometimes|required|email|unique:users,email,' . $user->id,
            'role' => 'sometimes|required|in:admin,teacher,supervisor',
            'password' => 'sometimes|nullable|string|min:6',
            'is_active' => 'sometimes|boolean'
        ]);

        if (isset($validated['first_name'])) $user->first_name = $validated['first_name'];
        if (isset($validated['last_name'])) $user->last_name = $validated['last_name'];
        if (isset($validated['email'])) $user->email = $validated['email'];
        if (isset($validated['role'])) $user->role = $validated['role'];
        if (isset($validated['is_active'])) $user->is_active = $validated['is_active'];
        if (!empty($validated['password'])) {
            $user->password = Hash::make($validated['password']);
        }

        $user->save();

        return response()->json([
            'message' => 'Staff identity updated successfully.',
            'staff' => $user
        ]);
    }

    /**
     * Revoke staff access (Delete)
     */
    public function destroy(Request $request, User $user)
    {
        if ($user->id === $request->user()->id) {
            return response()->json(['error' => 'Cannot revoke own access.'], 422);
        }

        if ($user->role === 'student') {
            return response()->json(['error' => 'Use identity management for students.'], 422);
        }

        $user->delete();

        return response()->json(['message' => 'Staff access revoked successfully.']);
    }
}
