<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    // Laravel is only an API. Redirect root visits to the frontend login page.
    return redirect(env('FRONTEND_URL', 'https://alpt.arabacademy.com') . '/login');
});

// Fallback route for serving files from storage if symlink is broken/missing
Route::get('storage/{path}', function ($path) {
    $fullPath = storage_path('app/public/' . $path);
    
    if (!file_exists($fullPath)) {
        abort(404);
    }
    
    return response()->file($fullPath);
})->where('path', '.*');
