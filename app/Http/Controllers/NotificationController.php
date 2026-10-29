<?php

namespace App\Http\Controllers;

use App\Models\Notification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    // Las notificaciones las crean los eventos (ejercicio nuevo, nota, vencimiento); aquí solo se consultan
    public function index(Request $request): JsonResponse
    {
        $notifications = $request->user()->userNotifications()
            ->when($request->boolean('unread'), fn ($query) => $query->unread())
            ->latest()
            ->limit(100)
            ->get();

        return response()->json($notifications);
    }

    public function unreadCount(Request $request): JsonResponse
    {
        return response()->json([
            'count' => $request->user()->userNotifications()->unread()->count(),
        ]);
    }

    public function show(Request $request, Notification $notification): JsonResponse
    {
        if (! $this->isOwner($request, $notification)) {
            return response()->json(['message' => 'No tienes acceso a esta notificación.'], 403);
        }

        return response()->json($notification);
    }

    public function markAsRead(Request $request, Notification $notification): JsonResponse
    {
        if (! $this->isOwner($request, $notification)) {
            return response()->json(['message' => 'No tienes acceso a esta notificación.'], 403);
        }

        $notification->update(['read_at' => $notification->read_at ?? now()]);

        return response()->json($notification);
    }

    public function markAllAsRead(Request $request): JsonResponse
    {
        $request->user()->userNotifications()->unread()->update(['read_at' => now()]);

        return response()->json(['message' => 'Notificaciones marcadas como leídas.']);
    }

    public function destroy(Request $request, Notification $notification): JsonResponse
    {
        if (! $this->isOwner($request, $notification)) {
            return response()->json(['message' => 'No tienes permiso para eliminar esta notificación.'], 403);
        }

        $notification->delete();

        return response()->json(['message' => 'Notificación eliminada.']);
    }

    private function isOwner(Request $request, Notification $notification): bool
    {
        return $notification->user_id === $request->user()->id;
    }
}
