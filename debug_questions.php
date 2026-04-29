<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\ExamAttempt;
use App\Models\Question;
use App\Models\Level;

$a = ExamAttempt::where('status', 'ongoing')->latest()->first();
if ($a) {
    $pos = $a->current_position;
    $skillId = $pos['skill_ids'][$pos['current_skill_index']];
    $levelNum = $pos['current_level'];
    $level = Level::where('skill_id', $skillId)->where('level_number', $levelNum)->first();

    echo "Exam ID: " . $a->exam_id . "\n";
    echo "Skill ID: " . $skillId . "\n";
    echo "Level ID: " . ($level ? $level->id : 'null') . " (Level Num: $levelNum)\n";

    if ($level) {
        echo "Level Defaults -> Standalone: {$level->default_standalone_quantity}, Passage: {$level->default_passage_quantity}, Legacy: {$level->default_question_count}\n";
        $qs = Question::where('exam_id', $a->exam_id)
            ->where('skill_id', $skillId)
            ->where('level_id', $level->id)
            ->get();

        echo "Total Questions Found in DB for this Skill/Level/Exam: " . $qs->count() . "\n";
        foreach ($qs as $q) {
            echo "ID: " . $q->id . " | Passage ID: " . ($q->passage_id ?: 'None') . " | Type: " . $q->type . "\n";
        }
        
        // Also check rules
        $rules = \App\Models\ExamQuestionRule::where('exam_id', $a->exam_id)
            ->where('skill_id', $skillId)
            ->where(function($q) use ($level) {
                $q->where('level_id', $level->id)->orWhereNull('level_id');
            })
            ->get();
        echo "Rules Found: " . $rules->count() . "\n";
        foreach($rules as $r) {
            echo "Rule ID: {$r->id} | Level ID: " . ($r->level_id ?: 'Global') . " | Standalone: {$r->standalone_quantity} | Passage: {$r->passage_quantity} | Legacy: {$r->quantity}\n";
        }
    }
} else {
    echo "No ongoing attempt found\n";
}
