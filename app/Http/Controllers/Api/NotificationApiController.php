<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\NotificationResource;
use App\Models\Notification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class NotificationApiController extends Controller
{
    /**
     * Get user notifications
     */
    public function index(Request $request)
    {
        try {
            $query = Notification::where('user_id', Auth::id())
                ->orderBy('created_at', 'desc');

            // Filter by read status
            if ($request->has('unread_only') && $request->unread_only) {
                $query->unread();
            }

            // Filter by type
            if ($request->has('type') && $request->type) {
                $query->byType($request->type);
            }

            $notifications = $query->paginate($request->get('per_page', 15));

            return response()->json([
                'status' => 'success',
                'data' => NotificationResource::collection($notifications),
                'unread_count' => Notification::where('user_id', Auth::id())->unread()->count()
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to retrieve notifications',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Mark notification as read
     */
    public function markAsRead(string $uuid)
    {
        try {
            $notification = Notification::where('uuid', $uuid)
                ->where('user_id', Auth::id())
                ->firstOrFail();

            $notification->markAsRead();

            return response()->json([
                'status' => 'success',
                'message' => 'Notification marked as read'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Notification not found'
            ], 404);
        }
    }

    /**
     * Mark all notifications as read
     */
    public function markAllAsRead()
    {
        try {
            Notification::where('user_id', Auth::id())
                ->unread()
                ->update(['read_at' => now()]);

            return response()->json([
                'status' => 'success',
                'message' => 'All notifications marked as read'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to mark notifications as read',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get unread notification count
     */
    public function unreadCount()
    {
        try {
            $count = Notification::where('user_id', Auth::id())->unread()->count();

            return response()->json([
                'status' => 'success',
                'data' => ['unread_count' => $count]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to get unread count',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete a notification
     */
    public function destroy(string $uuid)
    {
        try {
            $notification = Notification::where('uuid', $uuid)
                ->where('user_id', Auth::id())
                ->firstOrFail();

            $notification->delete();

            return response()->json([
                'status' => 'success',
                'message' => 'Notification deleted successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to delete notification'
            ], 500);
        }
    }

    /**
     * Delete all notifications for the authenticated user
     */
    public function deleteAllNotifications()
    {
        try {
            $deletedCount = Notification::where('user_id', Auth::id())->delete();

            return response()->json([
                'status' => 'success',
                'message' => "All notifications deleted successfully",
                'data' => [
                    'deleted_count' => $deletedCount
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to delete all notifications',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
