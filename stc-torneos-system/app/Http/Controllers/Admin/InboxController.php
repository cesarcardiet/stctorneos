<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AppNotification;
use App\Models\AppNotificationRecipient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class InboxController extends Controller
{
    public function feed(Request $request): JsonResponse
    {
        $user = $request->user();

        $items = AppNotification::query()
            ->with(['recipients' => fn ($query) => $query->where('user_id', $user->id)])
            ->where('status', 'sent')
            ->where(function ($query) use ($user) {
                $query
                    ->whereIn('audience', ['public', 'all'])
                    ->orWhereHas('recipients', fn ($q) => $q->where('user_id', $user->id));
            })
            ->accessibleTo($user)
            ->latest('sent_at')
            ->limit(25)
            ->get()
            ->map(function (AppNotification $notification) {
                $mine = $notification->recipients->first();

                return [
                    'id' => $notification->id,
                    'title' => $notification->title,
                    'body' => $notification->body,
                    'audience' => $notification->audienceLabel(),
                    'sent_at' => $notification->sent_at?->toIso8601String(),
                    'sent_label' => $notification->sent_at?->diffForHumans() ?? '',
                    'read' => $mine?->status === 'read',
                    'url' => route('admin.inbox.show', $notification),
                ];
            });

        $unreadTotal = AppNotificationRecipient::query()
            ->where('user_id', $user->id)
            ->where('status', '!=', 'read')
            ->count();

        return response()->json([
            'ok' => true,
            'data' => [
                'unread' => $unreadTotal,
                'items' => $items->values(),
            ],
        ]);
    }

    public function show(Request $request, AppNotification $notification): View
    {
        $user = $request->user();
        $isRecipient = $notification->recipients()->where('user_id', $user->id)->exists()
            || in_array($notification->audience, ['public', 'all'], true);

        abort_unless($notification->status === 'sent' && $isRecipient, 404);

        $recipient = $notification->recipients()->where('user_id', $user->id)->first();
        if ($recipient && $recipient->status !== 'read') {
            $recipient->update(['status' => 'read', 'read_at' => now()]);
            $notification->increment('read_count');
        }

        return view('admin.inbox.show', [
            'notification' => $notification,
        ]);
    }

    public function markRead(Request $request, AppNotification $notification): JsonResponse
    {
        $recipient = $notification->recipients()
            ->where('user_id', $request->user()->id)
            ->first();

        if ($recipient && $recipient->status !== 'read') {
            $recipient->update([
                'status' => 'read',
                'read_at' => now(),
            ]);
            $notification->increment('read_count');
        }

        return response()->json(['ok' => true, 'data' => ['read' => true]]);
    }

    public function markAllRead(Request $request): JsonResponse
    {
        $updated = AppNotificationRecipient::query()
            ->where('user_id', $request->user()->id)
            ->where('status', '!=', 'read')
            ->update([
                'status' => 'read',
                'read_at' => now(),
            ]);

        return response()->json(['ok' => true, 'data' => ['updated' => $updated]]);
    }
}
