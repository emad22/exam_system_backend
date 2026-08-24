<?php

namespace App\Policies;

use App\Models\Question;
use App\Models\User;

class QuestionPolicy
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
     * Teachers and supervisors can view the question list.
     * Students should never reach admin question routes (middleware handles this).
     */
    public function viewAny(User $user): bool
    {
        return in_array($user->role, ['admin', 'teacher', 'supervisor']);
    }

    /**
     * Teachers and supervisors can view individual questions.
     */
    public function view(User $user, Question $question): bool
    {
        return in_array($user->role, ['teacher', 'supervisor']);
    }

    /**
     * Teachers can create questions.
     */
    public function create(User $user): bool
    {
        return in_array($user->role, ['teacher', 'supervisor']);
    }

    /**
     * Teachers can update questions they created.
     * Supervisors and admins can update any question.
     */
    public function update(User $user, Question $question): bool
    {
        if ($user->role === 'supervisor') {
            return true;
        }

        if ($user->role === 'teacher') {
            return $question->created_by === $user->id;
        }

        return false;
    }

    /**
     * Only admin (via before) and supervisors can delete questions.
     */
    public function delete(User $user, Question $question): bool
    {
        return $user->role === 'supervisor';
    }

    /**
     * Only admin (via before) can bulk-update levels.
     */
    public function bulkUpdateLevel(User $user): bool
    {
        return in_array($user->role, ['supervisor']);
    }

    /**
     * Only admin (via before) and supervisors can upload media.
     */
    public function uploadMedia(User $user): bool
    {
        return in_array($user->role, ['teacher', 'supervisor']);
    }
}
