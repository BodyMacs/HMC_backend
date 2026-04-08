<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Models\Notification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class NotificationController extends Controller
{
    /**
     * GET /api/notifications
     * Retourne les notifications de l'utilisateur authentifié, paginées.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $query = Notification::where('user_id', $user->id)
            ->orderByDesc('created_at');

        // Filtre par type
        if ($request->filled('type') && $request->type !== 'all') {
            $query->where('type', $request->type);
        }

        // Filtre non-lus seulement
        if ($request->boolean('unread_only')) {
            $query->where('is_read', false);
        }

        $notifications = $query->paginate(20);

        return response()->json([
            'success' => true,
            'data' => [
                'notifications'  => $notifications->items(),
                'unread_count'   => Notification::where('user_id', $user->id)->where('is_read', false)->count(),
                'total'          => $notifications->total(),
                'current_page'   => $notifications->currentPage(),
                'last_page'      => $notifications->lastPage(),
            ],
        ]);
    }

    /**
     * GET /api/notifications/unread-count
     * Retourne uniquement le compteur de non-lus (utilisé par la sidebar).
     */
    public function unreadCount(Request $request): JsonResponse
    {
        $count = Notification::where('user_id', $request->user()->id)
            ->where('is_read', false)
            ->count();

        return response()->json(['success' => true, 'count' => $count]);
    }

    /**
     * POST /api/notifications/{id}/read
     * Marque une notification comme lue.
     */
    public function markRead(Request $request, int $id): JsonResponse
    {
        $notification = Notification::where('user_id', $request->user()->id)
            ->findOrFail($id);

        $notification->update(['is_read' => true]);

        return response()->json(['success' => true]);
    }

    /**
     * POST /api/notifications/read-all
     * Marque toutes les notifications de l'utilisateur comme lues.
     */
    public function markAllRead(Request $request): JsonResponse
    {
        Notification::where('user_id', $request->user()->id)
            ->where('is_read', false)
            ->update(['is_read' => true]);

        return response()->json(['success' => true, 'message' => 'Toutes les notifications ont été marquées comme lues.']);
    }

    /**
     * DELETE /api/notifications/{id}
     * Supprime une notification.
     */
    public function destroy(Request $request, int $id): JsonResponse
    {
        Notification::where('user_id', $request->user()->id)
            ->findOrFail($id)
            ->delete();

        return response()->json(['success' => true]);
    }

    /**
     * DELETE /api/notifications/clear-all
     * Supprime toutes les notifications de l'utilisateur.
     */
    public function clearAll(Request $request): JsonResponse
    {
        Notification::where('user_id', $request->user()->id)->delete();

        return response()->json(['success' => true, 'message' => 'Toutes les notifications ont été supprimées.']);
    }
}
