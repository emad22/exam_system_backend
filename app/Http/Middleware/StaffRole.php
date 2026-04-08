<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class StaffRole
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $allowed = ['admin', 'teacher', 'supervisor', 'demo'];
        
        if ($request->user() && in_array($request->user()->role, $allowed)) {
            return $next($request);
        }

        return response()->json([
            'message' => 'Unauthorized. Staff access required.'
        ], 403);
    }
}
