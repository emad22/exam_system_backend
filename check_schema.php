<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\Schema;

$table = 'exam_attempts';
$columns = Schema::getColumnListing($table);
foreach ($columns as $col) {
    echo "$col: " . Schema::getColumnType($table, $col) . "\n";
}

$table2 = 'exam_attempt_skills';
echo "\n--- $table2 ---\n";
$columns2 = Schema::getColumnListing($table2);
foreach ($columns2 as $col) {
    echo "$col: " . Schema::getColumnType($table2, $col) . "\n";
}
