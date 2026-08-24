<?php

namespace App\Policies;

use App\Models\Partner;
use App\Models\User;

class PartnerPolicy
{
    /**
     * Admins bypass all checks.
     */
    public function before(User $user, string $ability): ?bool
    {
        if ($user->role === 'admin') {
            return true;
        }

        return null;
    }

    /**
     * Admins (via before), supervisors, and teachers can list partners.
     */
    public function viewAny(User $user): bool
    {
        return in_array($user->role, ['supervisor', 'teacher']);
    }

    /**
     * A partner user can view their own profile.
     * Admin, supervisors, and teachers can view.
     */
    public function view(User $user, Partner $partner): bool
    {
        if (in_array($user->role, ['supervisor', 'teacher'])) {
            return true;
        }
 
        if ($user->role === 'partner' && $user->partner) {
            return $user->partner->id === $partner->id;
        }

        return false;
    }

    /**
     * Only admin can create partners (via before).
     */
    public function create(User $user): bool
    {
        return false;
    }

    /**
     * Only admin can update partners (via before).
     * Supervisors can update too.
     */
    public function update(User $user, Partner $partner): bool
    {
        return $user->role === 'supervisor';
    }

    /**
     * Only admin can delete partners (via before).
     */
    public function delete(User $user, Partner $partner): bool
    {
        return false;
    }

    /**
     * Only admin can hold/unhold partners (via before).
     */
    public function hold(User $user, Partner $partner): bool
    {
        return false;
    }
}
