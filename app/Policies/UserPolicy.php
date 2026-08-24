<?php

namespace App\Policies;

use App\Models\User;

class UserPolicy
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
     * Admins and supervisors can view staff / user list.
     */
    public function viewAny(User $user): bool
    {
        return in_array($user->role, ['admin', 'supervisor']);
    }

    /**
     * Staff can view their own profile or supervisor can view staff.
     */
    public function view(User $user, User $model): bool
    {
        if ($user->id === $model->id) {
            return true;
        }

        return in_array($user->role, ['admin', 'supervisor']);
    }

    /**
     * Only admins and supervisors can create staff accounts.
     */
    public function create(User $user): bool
    {
        return in_array($user->role, ['admin', 'supervisor']);
    }

    /**
     * Update user/staff details.
     */
    public function update(User $user, User $model): bool
    {
        if ($user->id === $model->id) {
            return true;
        }

        return in_array($user->role, ['admin', 'supervisor']);
    }

    /**
     * Only admin can delete staff / users.
     */
    public function delete(User $user, User $model): bool
    {
        if ($user->id === $model->id) {
            return false;
        }

        return false; // Only admin via before()
    }
}
