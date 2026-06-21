<?php

namespace App\Http\Controllers;

use App\Models\PushSubscription;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class NotificationController extends Controller
{
    public function subscribePush(Request $request): JsonResponse
    {
        $data = $request->validate([
            'endpoint'         => ['required', 'string', 'max:1024'],
            'keys.p256dh'      => ['required', 'string'],
            'keys.auth'        => ['required', 'string'],
            'content_encoding' => ['nullable', 'string'],
        ]);

        PushSubscription::updateOrCreate(
            ['user_id' => $request->user()->id, 'endpoint' => $data['endpoint']],
            [
                'p256dh_key'       => $data['keys']['p256dh'],
                'auth_token'       => $data['keys']['auth'],
                'content_encoding' => $data['content_encoding'] ?? 'aesgcm',
                'user_agent'       => substr((string) $request->userAgent(), 0, 500),
            ]
        );

        return response()->json(['ok' => true]);
    }

    public function unsubscribePush(Request $request): JsonResponse
    {
        $data = $request->validate(['endpoint' => ['required', 'string']]);
        PushSubscription::where('user_id', $request->user()->id)
            ->where('endpoint', $data['endpoint'])
            ->delete();

        return response()->json(['ok' => true]);
    }

    public function index(Request $request): View
    {
        $user   = $request->user();
        $filter = $request->get('filter', 'all');

        $q = $user->notifications();
        if ($filter === 'unread') {
            $q->whereNull('read_at');
        }

        $notifications  = $q->paginate(20)->withQueryString();
        $unreadCount    = $user->unreadNotifications()->count();

        return view('notifications.index', compact('notifications', 'unreadCount', 'filter'));
    }

    public function dropdown(Request $request): JsonResponse
    {
        $user = $request->user();
        $unread = $user->unreadNotifications()->limit(10)->get();
        return response()->json([
            'unread_count' => $user->unreadNotifications()->count(),
            'items' => $unread->map(fn ($n) => [
                'id'      => $n->id,
                'type'    => $n->data['type'] ?? 'info',
                'message' => $n->data['message'] ?? ($n->data['type'] ?? 'Notification'),
                'created' => $n->created_at?->diffForHumans(),
                'url'     => $n->data['url'] ?? null,
            ]),
        ]);
    }

    public function markRead(Request $request, string $id): RedirectResponse|JsonResponse
    {
        $n = $request->user()->notifications()->where('id', $id)->first();
        if ($n) $n->markAsRead();
        return $request->expectsJson() ? response()->json(['ok' => true]) : back();
    }

    public function markAllRead(Request $request): RedirectResponse|JsonResponse
    {
        $request->user()->unreadNotifications->markAsRead();
        return $request->expectsJson() ? response()->json(['ok' => true]) : back();
    }
}
