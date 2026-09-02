<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Response;

Route::get('/', function () {
    return redirect(env('FRONTEND_URL', 'https://alpt.arabacademy.com') . '/login');
});

// دالة لمعالجة عرض الملفات مع إرسال هيدرات CORS للحماية وتسهيل العرض في الفروانت اند
$storageHandler = function ($path) {
    $fullPath = storage_path('app/public/' . $path);

    if (!File::exists($fullPath)) {
        abort(404);
    }

    return response()->file($fullPath, [
        'Access-Control-Allow-Origin' => '*',
        'Access-Control-Allow-Methods' => 'GET, OPTIONS, HEAD',
        'Access-Control-Allow-Headers' => 'Content-Type, Authorization, X-Requested-With, Range',
        'Access-Control-Expose-Headers' => 'Content-Range, Content-Length, Accept-Ranges',
    ]);
};

// الراوت لو السيرفر بيبعت /storage مباشرة
Route::get('/storage/{path}', $storageHandler)->where('path', '.*');
Route::options('/storage/{path}', function () {
    return response('', 200)
        ->header('Access-Control-Allow-Origin', '*')
        ->header('Access-Control-Allow-Methods', 'GET, OPTIONS, HEAD')
        ->header('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Requested-With');
})->where('path', '.*');

// الراوت لو السيرفر بيبعت /api/storage
Route::get('/api/storage/{path}', $storageHandler)->where('path', '.*');
Route::options('/api/storage/{path}', function () {
    return response('', 200)
        ->header('Access-Control-Allow-Origin', '*')
        ->header('Access-Control-Allow-Methods', 'GET, OPTIONS, HEAD')
        ->header('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Requested-With');
})->where('path', '.*');
