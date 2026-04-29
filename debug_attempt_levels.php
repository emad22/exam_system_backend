<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\ExamAttempt;
use App\Models\ExamAttemptLevel;

$a = ExamAttempt::latest()->first();
if (!$a) {
    echo "No attempts found\n";
    exit;
}

echo "Attempt ID: {$a->id} for Student: {$a->student_id}\n";
$levels = ExamAttemptLevel::where('exam_attempt_id', $a->id)
    ->orderBy('created_at')
    ->get();

foreach ($levels as $l) {
    echo "Skill ID: {$l->skill_id} | Level Number: {$l->level_number} | Score: {$l->score} | Status: {$l->status} | Created: {$l->created_at}\n";
}
