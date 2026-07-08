<?php

namespace App\Http\Controllers;

use App\Http\Resources\NotificationResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    /**
     * A page of the current user's notifications, newest first. Cursor is the
     * timestamp (ms) of the oldest item already loaded, for load on scroll.
     */
    public function index(Request $request): JsonResponse
    {
        $query = $request->user()->notifications()->latest();

        if ($before = $request->query('before')) {
            $query->where('created_at', '<', now()->createFromTimestampMs((int) $before));
        }

        $notifications = $query->limit(20)->get();

        return response()->json([
            'notifications' => NotificationResource::collection($notifications)->resolve(),
            'unreadCount' => $request->user()->unreadNotifications()->count(),
        ]);
    }

    public function read(Request $request, string $notification): JsonResponse
    {
        $request->user()->notifications()->findOrFail($notification)->markAsRead();

        return response()->json(['unreadCount' => $request->user()->unreadNotifications()->count()]);
    }

    public function readAll(Request $request): JsonResponse
    {
        $request->user()->unreadNotifications->markAsRead();

        return response()->json(['unreadCount' => 0]);
    }
}
