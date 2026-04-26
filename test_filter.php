<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';

$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
$response = $kernel->handle(
    $request = Illuminate\Http\Request::capture()
);

$s = \App\Models\Student::find(1);
$exam = \App\Models\Exam::with('skills')->find(1);

$allowedSkillIdentifiers = array_filter((array) $s->assigned_skills);
echo "Allowed Identifiers: " . json_encode($allowedSkillIdentifiers) . "\n";

$filteredSkills = $exam->skills->filter(function($skill) use ($allowedSkillIdentifiers) {
    $skillName = strtolower(trim($skill->name));
    $skillCode = strtolower(trim($skill->short_code));
    
    foreach ($allowedSkillIdentifiers as $idOrCode) {
        $match = strtolower(trim($idOrCode));
        echo "Testing Skill {$skill->id} ({$skillName}/{$skillCode}) against match '{$match}'\n";
        if ($skill->id == $match || $skillName == $match || $skillCode == $match) {
            echo "  - MATCH FOUND!\n";
            return true;
        }
    }
    return false;
});

echo "Final Filtered Skills Count: " . $filteredSkills->count() . "\n";
foreach ($filteredSkills as $sk) {
    echo "  - " . $sk->name . "\n";
}
