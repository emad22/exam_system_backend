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
     * Update student profile (Avatar and Password only)
     */
    public function update(Request $request)
    {
        $user = $request->user();

        $validated = $request->validate([
            'avatar' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
            'password' => ['nullable', 'confirmed', Password::min(6)],
        ]);

        $data = [];

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
