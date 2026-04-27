<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';

$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$levels = \App\Models\Level::where('skill_id', 1)->where('is_active', true)->get(); 
foreach($levels as $l) { 
    echo "Level {$l->level_number}: std={$l->default_standalone_quantity}, pass={$l->default_passage_quantity}, leg={$l->default_question_count}\n"; 
}
