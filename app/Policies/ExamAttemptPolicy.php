<?php

namespace App\Policies;

use App\Models\ExamAttempt;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class ExamAttemptPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return false;
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, ExamAttempt $examAttempt): bool
    {
        if (in_array($user->role, ['admin', 'teacher', 'supervisor'])) {
            return true;
        }

        // Check if it belongs to the student profile
        if ($user->student && (int) $examAttempt->student_id === (int) $user->student->id) {
            return true;
        }

        // Check if it belongs to the user ID (for demo or direct user link)
        if ($examAttempt->user_id && (int) $examAttempt->user_id === (int) $user->id) {
            return true;
        }

        return false;
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, ExamAttempt $examAttempt): bool
    {
        return $this->view($user, $examAttempt);
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, ExamAttempt $examAttempt): bool
    {
        return false;
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, ExamAttempt $examAttempt): bool
    {
        return false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, ExamAttempt $examAttempt): bool
    {
        return false;
    }
}
