<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rules\Password;

class ProfileController extends Controller
{
    /**
     * Update user profile
     */
    public function update(Request $request)
    {
        $user = $request->user();

        $validated = $request->validate([

            'first_name' => 'nullable|string|max:255',
            'last_name' => 'nullable|string|max:255',
            'phone' => 'nullable|string|max:20',
            'birth_date' => 'nullable|date',
            'gender' => 'nullable|in:male,female,other',
            'address' => 'nullable|string|max:500',
            'city' => 'nullable|string|max:255',
            'state' => 'nullable|string|max:255',
            'country' => 'nullable|string|max:255',
            'religion' => 'nullable|string|max:255',
            'occupation' => 'nullable|string|max:255',
            'avatar' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
            'password' => ['nullable', 'confirmed', Password::min(6)],
        ]);

        $data = $request->only([
            'first_name',
            'last_name',
            'phone',
            'birth_date',
            'gender',
            'address',
            'city',
            'state',
            'country',
            'religion',
            'occupation'
        ]);

        // Handle Avatar Upload
        if ($request->hasFile('avatar')) {
            // Delete old avatar if exists
            if ($user->avatar) {
                Storage::disk('public')->delete($user->avatar);
            }

            $path = $request->file('avatar')->store('avatars', 'public');
            $data['avatar'] = $path;
        }

        // Handle Password Update
        if ($request->filled('password')) {
            $data['password'] = Hash::make($request->password);
        }

        if (!empty($data)) {
            $user->update($data);
        }

        return response()->json([
            'message' => 'Profile updated successfully',
            'user' => $user->fresh()
        ]);
    }

    /**
     * Get current user profile
     */
    public function show(Request $request)
    {
        return response()->json($request->user()->load('student'));
    }
}
