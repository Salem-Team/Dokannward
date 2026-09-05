<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Notification;
use App\Providers\AppServiceProvider;
use Illuminate\Http\JsonResponse;

class NotificationController extends Controller
{
    /**
     * Get unread notifications count.
     */
    public function unreadCount(): JsonResponse
    {
        return response()->json([
            'count' => Notification::unread()->count(),
        ]);
    }

    /**
     * Mark a single notification as read.
     */
    public function markAsRead(string $id): JsonResponse
    {
        $notification = Notification::findOrFail($id);
        $wasUnread = ! $notification->is_read;

        if ($wasUnread) {
            $notification->markAsRead();
        }

        return response()->json([
            'success' => true,
            'was_unread' => $wasUnread,
            'unread_count' => Notification::unread()->count(),
        ]);
    }

    /**
     * Mark all notifications as read.
     */
    public function markAllAsRead(): JsonResponse
    {
        Notification::unread()->update([
            'is_read' => true,
            'read_at' => now(),
        ]);

        // Mass updates skip Eloquent saved events — bust chrome cache explicitly.
        AppServiceProvider::forgetChromeCache();

        return response()->json([
            'success' => true,
            'unread_count' => 0,
        ]);
    }

    /**
     * Delete every notification.
     */
    public function clearAll(): JsonResponse
    {
        Notification::query()->delete();
        AppServiceProvider::forgetChromeCache();

        return response()->json([
            'success' => true,
            'unread_count' => 0,
        ]);
    }

    /**
     * Delete one notification.
     */
    public function destroy(string $id): JsonResponse
    {
        Notification::findOrFail($id)->delete();

        return response()->json([
            'success' => true,
            'unread_count' => Notification::unread()->count(),
        ]);
    }
}
