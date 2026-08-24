<?php

namespace App\Policies;

use App\Models\Exam;
use App\Models\User;

class ExamPolicy
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
     * Staff can list exams.
     */
    public function viewAny(User $user): bool
    {
        return in_array($user->role, ['admin', 'teacher', 'supervisor', 'demo']);
    }

    /**
     * Staff can view individual exam details.
     */
    public function view(User $user, Exam $exam): bool
    {
        return in_array($user->role, ['admin', 'teacher', 'supervisor', 'demo']);
    }

    /**
     * Admin and supervisors can create exams.
     */
    public function create(User $user): bool
    {
        return in_array($user->role, ['admin', 'supervisor']);
    }

    /**
     * Admin and supervisors can update exams.
     */
    public function update(User $user, Exam $exam): bool
    {
        return in_array($user->role, ['admin', 'supervisor']);
    }

    /**
     * Admin and supervisors can delete exams.
     */
    public function delete(User $user, Exam $exam): bool
    {
        return $user->role === 'supervisor';
    }

    /**
     * Admin and supervisors can set default exam.
     */
    public function setDefault(User $user, Exam $exam): bool
    {
        return in_array($user->role, ['admin', 'supervisor']);
    }
}
