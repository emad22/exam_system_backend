<?php

namespace App\Policies;

use App\Models\ProctoringSession;
use App\Models\User;

class ProctoringSessionPolicy
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
     * Staff can view the proctoring session list.
     */
    public function viewAny(User $user): bool
    {
        return in_array($user->role, ['admin', 'teacher', 'supervisor']);
    }

    /**
     * Staff can view any session.
     * A student can only view their own session.
     */
    public function view(User $user, ProctoringSession $session): bool
    {
        if (in_array($user->role, ['teacher', 'supervisor'])) {
            return true;
        }

        // Student viewing their own session
        if ($user->student) {
            return $session->student_id === $user->student->id;
        }

        return false;
    }

    /**
     * Students can initiate their own proctoring session.
     * Staff cannot create sessions on behalf of students directly.
     */
    public function create(User $user): bool
    {
        return in_array($user->role, ['student', 'demo']);
    }

    /**
     * Students can update their own ongoing session (e.g. start/pause/resume).
     * Staff can update any session (e.g. force-end from admin panel).
     */
    public function update(User $user, ProctoringSession $session): bool
    {
        if (in_array($user->role, ['teacher', 'supervisor'])) {
            return true;
        }

        if ($user->student) {
            return $session->student_id === $user->student->id
                && in_array($session->status, ['pending', 'active', 'paused']);
        }

        return false;
    }

    /**
     * Only admin (via before) and supervisors can delete sessions.
     */
    public function delete(User $user, ProctoringSession $session): bool
    {
        return $user->role === 'supervisor';
    }

    /**
     * Only admin (via before) and supervisors can review violations.
     */
    public function reviewViolation(User $user, ProctoringSession $session): bool
    {
        return in_array($user->role, ['supervisor', 'teacher']);
    }

    /**
     * Only admin (via before) can export session reports.
     */
    public function exportReport(User $user, ProctoringSession $session): bool
    {
        return in_array($user->role, ['supervisor', 'teacher']);
    }

    /**
     * Only admin and supervisors can bulk delete sessions.
     */
    public function bulkDelete(User $user): bool
    {
        return $user->role === 'supervisor';
    }
}
