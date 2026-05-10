<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Question;
$q = Question::find(50);
if ($q) {
    $options = $q->options()->where('is_correct', true)->get(['id', 'option_text'])->toArray();
    echo json_encode($options, JSON_UNESCAPED_UNICODE);
} else {
    echo "Question not found";
}
