<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\Level;
use App\Models\Skill;

$counts = Level::groupBy('skill_id')->selectRaw('skill_id, count(*) as count')->get();
foreach ($counts as $c) {
    $skill = Skill::find($c->skill_id);
    echo "Skill: {$skill->name} | Levels: {$c->count}\n";
}
