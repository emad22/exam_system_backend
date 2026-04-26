<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';

$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

// Create a simulated request as user 3 (Student 1)
$user = \App\Models\User::find(3); // Based on SQL dump, student user is ID 3
if (!$user) {
    die("User 3 not found\n");
}

$request = Illuminate\Http\Request::create('/api/exams', 'GET');
$request->setUserResolver(function () use ($user) {
    return $user;
});

// Handle the request directly through the controller to bypass auth middleware if needed
// Actually, let's just instantiate the controller and call index
$controller = $app->make(\App\Http\Controllers\Api\ExamController::class);
$response = $controller->index($request);

echo $response->getContent();
echo "\n";
