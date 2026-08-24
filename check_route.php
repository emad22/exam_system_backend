<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$request = Illuminate\Support\Facades\Request::create('GET', '/api/v1/admin/students');
$route = Illuminate\Support\Facades\Route::getRoutes()->match($request);
echo "Middleware: " . implode(', ', $route->gatherMiddleware()) . "\n";
