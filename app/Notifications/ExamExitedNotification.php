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
        
        // Safely get skill name from current position
        $skillName = 'Unknown Skill';
        $pos = $this->attempt->current_position;
        if (isset($pos['skill_ids']) && isset($pos['current_skill_index'])) {
            $skillId = $pos['skill_ids'][$pos['current_skill_index']];
            $skill = \App\Models\Skill::find($skillId);
            if ($skill) {
                $skillName = $skill->name;
            }
        }

        return [
            'attempt_id' => $this->attempt->id,
            'student_name' => $studentName,
            'skill_name' => $skillName,
            'message' => "Student {$studentName} has exited the {$skillName} exam.",
            'type' => 'exam_exited'
        ];
    }
}
