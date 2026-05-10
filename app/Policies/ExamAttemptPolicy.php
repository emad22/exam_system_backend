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
        if ($user->role === 'admin' || $user->role === 'teacher') {
            return true;
        }

        // Check if it belongs to the student profile
        if ($user->student && $examAttempt->student_id === $user->student->id) {
            return true;
        }

        // Check if it belongs to the demo user ID
        if ($examAttempt->user_id === $user->id) {
            return true;
        }

        return false;
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, ExamAttempt $examAttempt): bool
    {
        // For students, they can only update their own ongoing attempts
        if ($user->role === 'admin' || $user->role === 'teacher') {
            return true;
        }

        if ($examAttempt->status !== 'ongoing') {
            return false;
        }

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
