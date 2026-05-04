<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class SkillCompletedNotification extends Notification
{
    use Queueable;

    protected $attempt;
    protected $skill;

    /**
     * Create a new notification instance.
     */
    public function __construct($attempt, $skill)
    {
        $this->attempt = $attempt;
        $this->skill = $skill;
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {

        $studentName = $this->attempt->student->user->first_name . ' ' . $this->attempt->student->user->last_name;
        return [
            'attempt_id' => $this->attempt->id,
            'student_name' => $studentName,
            'skill_name' => $this->skill->name,
            'message' => "Student {$studentName} has finished the {$this->skill->name} skill.",
            'type' => 'skill_completed'
        ];
    }
}
