<?php

namespace App\Policies;

use App\Models\Package;
use App\Models\User;

class PackagePolicy
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
     * Staff and partners can list packages.
     */
    public function viewAny(User $user): bool
    {
        return in_array($user->role, ['admin', 'teacher', 'supervisor', 'demo', 'partner']);
    }

    /**
     * Staff and partners can view a single package.
     */
    public function view(User $user, Package $package): bool
    {
        return in_array($user->role, ['admin', 'teacher', 'supervisor', 'demo', 'partner']);
    }

    /**
     * Admin and supervisors can create packages.
     */
    public function create(User $user): bool
    {
        return in_array($user->role, ['admin', 'supervisor']);
    }

    /**
     * Admin and supervisors can update packages.
     */
    public function update(User $user, Package $package): bool
    {
        return in_array($user->role, ['admin', 'supervisor']);
    }

    /**
     * Admin and supervisors can delete packages.
     */
    public function delete(User $user, Package $package): bool
    {
        return $user->role === 'supervisor';
    }
}
