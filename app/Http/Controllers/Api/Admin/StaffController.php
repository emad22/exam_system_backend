<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Partner;
use App\Http\Requests\Admin\Staff\StoreStaffRequest;
use App\Http\Requests\Admin\Staff\UpdateStaffRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class StaffController extends Controller
{
    /**
     * Get all staff members
     */
    public function index(Request $request)
    {
        $staff = User::with('partner')->where('role', '!=', 'student')
            ->orderBy('role')
            ->paginate(50);
        return response()->json($staff);
    }

    /**
     * Get a specific staff member with partner details if applicable
     */
    public function show(User $user)
    {
        if ($user->role === 'student') {
            return response()->json(['error' => 'Not a staff member.'], 422);
        }
        return response()->json($user->load('partner'));
    }

    /**
     * Provision a new staff identity
     */
    public function store(StoreStaffRequest $request)
    {
        $validated = $request->validated();

        $staff = User::create([
            'first_name' => $validated['first_name'],
            'last_name' => $validated['last_name'],
            'username' => $validated['username'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'role' => $validated['role'],
            'phone' => $validated['phone'] ?? null,
            'country' => $validated['country'] ?? null,
            'is_active' => $validated['is_active'] ?? true,
        ]);

        if ($staff->role === 'partner') {
            $mode = $validated['proctoring_mode'] ?? ($validated['proctoring_required'] ?? false ? 'full' : 'none');
            Partner::create([
                'user_id' => $staff->id,
                'partner_name' => $validated['partner_name'] ?? ($validated['first_name'] . ' ' . $validated['last_name']),
                'website' => $validated['website'] ?? null,
                'note' => $validated['note'] ?? null,
                'proctoring_mode' => $mode,
                'proctoring_required' => in_array($mode, ['full', 'identity_only'], true),
                'r_date' => now(),
            ]);
        }

        return response()->json([
            'message' => 'Staff identity provisioned successfully.',
            'staff' => $staff->load('partner')
        ], 201);
    }

    /**
     * Update an existing staff role or identity
     */
    public function update(UpdateStaffRequest $request, User $user)
    {
        if ($user->role === 'student') {
            return response()->json(['error' => 'Use student identity management for this account.'], 422);
        }

        $validated = $request->validated();

        if (isset($validated['first_name'])) $user->first_name = $validated['first_name'];
        if (isset($validated['last_name'])) $user->last_name = $validated['last_name'];
        if (isset($validated['username'])) $user->username = $validated['username'];
        if (isset($validated['email'])) $user->email = $validated['email'];
        if (isset($validated['role'])) $user->role = $validated['role'];
        if (isset($validated['phone'])) $user->phone = $validated['phone'];
        if (isset($validated['country'])) $user->country = $validated['country'];
        if (isset($validated['is_active'])) $user->is_active = $validated['is_active'];
        
        if (!empty($validated['password'])) {
            $user->password = Hash::make($validated['password']);
        }

        $user->save();

        if ($user->role === 'partner') {
            $existingPartner = Partner::where('user_id', $user->id)->first();
            $mode = $validated['proctoring_mode'] ?? ($existingPartner?->proctoring_mode ?? ($validated['proctoring_required'] ?? false ? 'full' : 'none'));
            Partner::updateOrCreate(
                ['user_id' => $user->id],
                [
                    'partner_name' => $validated['partner_name'] ?? ($user->first_name . ' ' . $user->last_name),
                    'website' => $validated['website'] ?? null,
                    'note' => $validated['note'] ?? null,
                    'proctoring_mode' => $mode,
                    'proctoring_required' => in_array($mode, ['full', 'identity_only'], true),
                ]
            );
        }

        return response()->json([
            'message' => 'Staff identity updated successfully.',
            'staff' => $user->load('partner')
        ]);
    }

    /**
     * Revoke staff access
     */
    public function destroy(Request $request, User $user)
    {
        if ($user->id === $request->user()->id) {
            return response()->json(['error' => 'Cannot revoke own access.'], 422);
        }

        if ($user->role === 'student') {
            return response()->json(['error' => 'Use identity management for students.'], 422);
        }

        // Note: Linked Partner record will remain unless deleted specifically, 
        // or cascading delete is set on migrations. 
        // Admin might want to keep the Partner profile for history.
        $user->delete();

        return response()->json(['message' => 'Staff access revoked successfully.']);
    }
}
