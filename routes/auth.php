<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ProfileController;

// Public Auth Routes
Route::as('auth.')->group(function () {
    Route::post('/login', [AuthController::class, 'login'])
        ->middleware('throttle:5,1')
        ->name('login');
    Route::post('/register', [AuthController::class, 'register'])
        ->middleware('throttle:3,1')
        ->name('register');
});

// Authenticated Profile Routes
Route::middleware(['auth:sanctum','single.session'])->as('profile.')->group(function () {
    Route::get('/profile', [ProfileController::class, 'show'])->name('show');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('update');
    
    Route::get('/user', [AuthController::class, 'me'])->name('user');
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
});
