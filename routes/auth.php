<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ProfileController;

// Public Auth Routes
Route::as('auth.')->group(function () {
    Route::post('/login', [AuthController::class, 'login'])->name('login');
    Route::post('/register', [AuthController::class, 'register'])->name('register');
});

// Authenticated Profile Routes
Route::middleware('auth:sanctum')->as('profile.')->group(function () {
    Route::get('/profile', [ProfileController::class, 'show'])->name('show');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('update');
    
    Route::get('/user', [AuthController::class, 'me'])->name('user');
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
});
