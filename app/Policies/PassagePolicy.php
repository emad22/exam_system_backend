<?php

namespace App\Policies;

use App\Models\Passage;
use App\Models\User;

class PassagePolicy
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
     * Staff can view passages.
     */
    public function viewAny(User $user): bool
    {
        return in_array($user->role, ['admin', 'teacher', 'supervisor', 'demo']);
    }

    /**
     * Staff can view an individual passage.
     */
    public function view(User $user, Passage $passage): bool
    {
        return in_array($user->role, ['admin', 'teacher', 'supervisor', 'demo']);
    }

    /**
     * Staff can create passages.
     */
    public function create(User $user): bool
    {
        return in_array($user->role, ['admin', 'teacher', 'supervisor']);
    }

    /**
     * Staff can update passages.
     */
    public function update(User $user, Passage $passage): bool
    {
        return in_array($user->role, ['admin', 'teacher', 'supervisor']);
    }

    /**
     * Admin and supervisors can delete passages.
     */
    public function delete(User $user, Passage $passage): bool
    {
        return $user->role === 'supervisor';
    }
}
