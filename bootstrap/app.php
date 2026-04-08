<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'is_admin'      => \App\Http\Middleware\AdminRole::class,
            'is_teacher'    => \App\Http\Middleware\TeacherRole::class,
            'is_student'    => \App\Http\Middleware\StudentRole::class,
            'is_demo'       => \App\Http\Middleware\DemoRole::class,
            'is_supervisor' => \App\Http\Middleware\SupervisorRole::class,
            'is_staff'      => \App\Http\Middleware\StaffRole::class,
            'is_student_or_demo' => \App\Http\Middleware\StudentOrDemoRole::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
