<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\Question;
use App\Models\Level;
use App\Models\ExamAttempt;

$a = ExamAttempt::where('status', 'ongoing')->latest()->first();
if (!$a) {
    echo "No ongoing attempt\n";
    exit;
}

$examId = $a->exam_id;
$pos = $a->current_position;
$skillId = $pos['skill_ids'][$pos['current_skill_index']];

$levels = Level::where('skill_id', $skillId)->where('is_active', true)->get();
$grandTotal = 0;
foreach ($levels as $lv) {
    $count = Question::where('exam_id', $examId)
        ->where('skill_id', $skillId)
        ->where('level_id', $lv->id)
        ->count();
    echo "Level {$lv->level_number} (ID: {$lv->id}): $count questions\n";
    $grandTotal += $count;
}
echo "Grand Total: $grandTotal\n";
