<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    /**
     * Get unread notifications for the authenticated user.
     */
    public function index(Request $request)
    {
        $user = $request->user();
        return response()->json($user->unreadNotifications);
    }

    /**
     * Mark notifications as read. If ID is provided, marks that specific one.
     */
    public function markAsRead(Request $request)
    {
        $user = $request->user();
        if ($request->has('id')) {
            $notification = $user->notifications()->where('id', $request->id)->first();
            if ($notification) {
                $notification->markAsRead();
            }
        } else {
            $user->unreadNotifications->markAsRead();
        }
        return response()->json(['success' => true]);
    }
}
