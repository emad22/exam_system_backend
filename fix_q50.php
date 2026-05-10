<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\QuestionOption;

// Fix question 50 options order
QuestionOption::where('id', 356)->update(['sort_order' => 10]); // الحية
QuestionOption::where('id', 355)->update(['sort_order' => 20]); // الرسمية
QuestionOption::where('id', 354)->update(['sort_order' => 30]); // الخط

echo "Question 50 options order fixed.\n";
