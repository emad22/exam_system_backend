<?php

namespace App\Policies;

use App\Models\Skill;
use App\Models\User;

class SkillPolicy
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
     * Staff can list skills.
     */
    public function viewAny(User $user): bool
    {
        return in_array($user->role, ['admin', 'teacher', 'supervisor', 'demo']);
    }

    /**
     * Staff can view a skill.
     */
    public function view(User $user, Skill $skill): bool
    {
        return in_array($user->role, ['admin', 'teacher', 'supervisor', 'demo']);
    }

    /**
     * Admin and supervisors can create skills.
     */
    public function create(User $user): bool
    {
        return in_array($user->role, ['admin', 'supervisor']);
    }

    /**
     * Admin and supervisors can update skills.
     */
    public function update(User $user, Skill $skill): bool
    {
        return in_array($user->role, ['admin', 'supervisor']);
    }

    /**
     * Admin and supervisors can delete skills.
     */
    public function delete(User $user, Skill $skill): bool
    {
        return false; // Only admin via before()
    }

    /**
     * Admin and supervisors can bulk-update skill levels.
     */
    public function bulkUpdateLevels(User $user): bool
    {
        return in_array($user->role, ['admin', 'supervisor']);
    }
}
