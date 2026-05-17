<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    // Laravel is only an API. Redirect root visits to the frontend login page.
    return redirect(env('FRONTEND_URL', 'https://alpt.arabacademy.com') . '/login');
});
