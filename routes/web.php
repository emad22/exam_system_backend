<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Response;

Route::get('/', function () {
    return redirect(env('FRONTEND_URL', 'https://alpt.arabacademy.com') . '/login');
});

// دالة لمعالجة عرض الصور عشان نستخدمها في المسارين
$storageHandler = function ($path) {
    $fullPath = storage_path('app/public/' . $path);

    if (!File::exists($fullPath)) {
        abort(404);
    }

    $file = File::get($fullPath);
    $type = File::mimeType($fullPath);

    return Response::make($file, 200)->header("Content-Type", $type);
};

// الراوت لو السيرفر بيبعت /storage مباشرة
Route::get('/storage/{path}', $storageHandler)->where('path', '.*');

// الراوت لو السيرفر بيبعت /api/storage (زي ما بيحصل عندك)
Route::get('/api/storage/{path}', $storageHandler)->where('path', '.*');
