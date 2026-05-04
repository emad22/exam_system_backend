<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class ExamExitedNotification extends Notification
{
    use Queueable;

    protected $attempt;

    public function __construct($attempt)
    {
        $this->attempt = $attempt;
    }

    public function via($notifiable)
    {
        return ['database'];
    }

    public function toArray($notifiable)
    {
        $studentName = $this->attempt->student->user->first_name . ' ' . $this->attempt->student->user->last_name;
        return [
            'attempt_id' => $this->attempt->id,
            'student_name' => $studentName,
            'skill_name' => $this->attempt->skill->name,
            'message' => "Student {$studentName} has exited the exam.",
            'type' => 'exam_exited'
        ];
    }
}
