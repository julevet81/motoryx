<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\Notification;
use App\Traits\ApiResponser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    use ApiResponser;

    public function index(Request $request): JsonResponse
    {
        $user    = $request->user();
        $perPage = min((int) $request->input('per_page', 20), 50);

        $notifications = Notification::where('user_id', $user->id)
            ->latest('created_at')
            ->paginate($perPage);

        $unreadCount = Notification::where('user_id', $user->id)
            ->whereNull('read_at')
            ->count();

        return $this->paginatedResponse(
            $notifications,
            fn($n) => $this->notificationResource($n),
        ) + ['unread_count' => $unreadCount]; // append to response
    }

    /**
     * Mark one notification as read.
     */
    public function markRead(int $id): JsonResponse
    {
        $notification = Notification::where('user_id', request()->user()->id)
            ->findOrFail($id);

        $notification->update(['read_at' => now()]);

        return $this->successResponse(null, 'Marked as read.');
    }

    /**
     * Mark all notifications as read.
     */
    public function markAllRead(Request $request): JsonResponse
    {
        Notification::where('user_id', $request->user()->id)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        return $this->successResponse(null, 'All notifications marked as read.');
    }

    public function destroy(int $id): JsonResponse
    {
        Notification::where('user_id', request()->user()->id)
            ->findOrFail($id)
            ->delete();

        return $this->noContentResponse();
    }

    private function notificationResource(Notification $n): array
    {
        return [
            'id'         => $n->id,
            'title'      => $n->title,
            'body'       => $n->body,
            'type'       => $n->type,
            'data'       => $n->data,
            'is_read'    => $n->isRead(),
            'read_at'    => $n->read_at?->toIso8601String(),
            'created_at' => $n->created_at->toIso8601String(),
        ];
    }
}
