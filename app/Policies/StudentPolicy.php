<?php

namespace App\Policies;

use App\Models\Student;
use App\Models\User;

class StudentPolicy
{
    /**
     * Admins and supervisors can bypass all checks.
     */
    public function before(User $user, string $ability): ?bool
    {
        if ($user->role === 'admin') {
            return true;
        }

        return null;
    }

    /**
     * Staff (teacher/supervisor) can list students.
     * Partners can only see their own students (enforced at query level).
     */
    public function viewAny(User $user): bool
    {
        return in_array($user->role, ['admin', 'teacher', 'supervisor', 'partner']);
    }

    /**
     * Staff can view any student profile.
     * Partners can only view students assigned to them.
     */
    public function view(User $user, Student $student): bool
    {
        if (in_array($user->role, ['teacher', 'supervisor'])) {
            return true;
        }

        if ($user->role === 'partner' && $user->partner) {
            return $student->partner_id === $user->partner->id;
        }

        return false;
    }

    /**
     * Only admin (via before) and supervisors can create students.
     */
    public function create(User $user): bool
    {
        return in_array($user->role, ['admin', 'supervisor']);
    }

    /**
     * Admins and supervisors can update any student.
     * Partners cannot update students.
     */
    public function update(User $user, Student $student): bool
    {
        return $user->role === 'supervisor';
    }

    /**
     * Only admin can delete (via before).
     */
    public function delete(User $user, Student $student): bool
    {
        return false;
    }

    /**
     * Only admin can bulk-delete (via before).
     */
    public function bulkDelete(User $user): bool
    {
        return false;
    }

    /**
     * Only admin can reset exam attempts (via before).
     */
    public function resetAttempts(User $user, Student $student): bool
    {
        return false;
    }

    /**
     * Admin and supervisors can import students.
     */
    public function import(User $user): bool
    {
        return in_array($user->role, ['admin', 'supervisor']);
    }

    /**
     * Admin and supervisors can bulk-update skills.
     */
    public function bulkUpdateSkills(User $user): bool
    {
        return in_array($user->role, ['admin', 'supervisor']);
    }

    /**
     * Admin and supervisors can toggle candidate verification bypass.
     */
    public function toggleBypass(User $user, Student $student): bool
    {
        return in_array($user->role, ['admin', 'supervisor']);
    }
}
