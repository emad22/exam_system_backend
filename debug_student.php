<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';

$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
$response = $kernel->handle(
    $request = Illuminate\Http\Request::capture()
);

$s = \App\Models\Student::with('package')->find(1);
echo "Assigned Skills: \n";
print_r($s->assigned_skills);
$assignedExamIds = $s->configs()->pluck('exam_id')->toArray();
if ($s->package && $s->package->exam_id) {
    if (!in_array($s->package->exam_id, $assignedExamIds)) {
        $assignedExamIds[] = $s->package->exam_id;
    }
}
echo "Assigned Exam IDs: " . implode(', ', $assignedExamIds) . "\n";

$exams = \App\Models\Exam::whereIn('id', $assignedExamIds)->with('skills')->get();
foreach ($exams as $exam) {
    echo "Exam: " . $exam->title . " (ID: " . $exam->id . ")\n";
    echo "  Total Skills in Exam: " . $exam->skills->count() . "\n";
    foreach ($exam->skills as $sk) {
        echo "    - Skill: " . $sk->name . " (Code: " . $sk->short_code . ")\n";
    }
}

