<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class NotificationController extends Controller
{
    // Get all notifications for the authenticated user
    public function getNotifications()
    {
        $user = Auth::user();
        $notifications = $user->notifications()->latest()->get();
        return response()->json($notifications);
    }

    // Mark a notification as read
    public function isReadNotification($id)
    {
        $notification = Auth::user()->notifications()->find($id);
        if (!$notification) {
            return response()->json(['success' => false, 'message' => 'Notification not found.'], 404);
        }
        $notification->read_at = now();
        $notification->save();
        return response()->json(['success' => true, 'message' => 'Notification marked as read.']);
    }

    // Delete a notification
    // public function deleteNotification($id)
    // {
        
    //     $notification = Notification::find($id);
    //     if (!$notification) {
    //         return response()->json(['success' => false, 'message' => 'Notification not found.'], 404);
    //     }
    //     $notification->delete();
    //     return response()->json(['success' => true]);
    // }

    // Clear all notifications for the authenticated user
    public function clearAllNotifications()
    {
        $user = Auth::user();
        $user->notifications()->delete();
        return response()->json(['success' => true]);
    }
}
