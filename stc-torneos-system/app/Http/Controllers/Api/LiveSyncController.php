<?php

namespace App\Http\Controllers\Api;

use App\Models\AppNotification;
use App\Models\FixtureMatch;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LiveSyncController extends ApiController
{
    /**
     * Lightweight snapshot for auto-refresh (polling) of live scores.
     */
    public function matches(Request $request): JsonResponse
    {
        $since = $request->query('since');

        $query = FixtureMatch::query()
            ->with(['homeTeam.delegation', 'awayTeam.delegation', 'field', 'category.tournament'])
            ->where(function ($builder) {
                $builder
                    ->where('status', 'live')
                    ->orWhere(function ($recent) {
                        $recent
                            ->whereIn('status', ['finished', 'validated'])
                            ->where('updated_at', '>=', now()->subMinutes(30));
                    });
            })
            ->when($request->filled('tournament_id'), fn ($q) => $q->where('tournament_id', $request->integer('tournament_id')))
            ->when($request->filled('category_id'), fn ($q) => $q->where('category_id', $request->integer('category_id')))
            ->when($request->filled('match_id'), fn ($q) => $q->where('id', $request->integer('match_id')))
            ->orderBy('scheduled_at');

        $matches = $query->get();

        $revision = $matches
            ->map(fn (FixtureMatch $match) => optional($match->updated_at)->timestamp ?? 0)
            ->max() ?: 0;

        if (is_numeric($since) && (int) $since >= $revision && $matches->where('status', 'live')->isNotEmpty()) {
            return $this->ok([
                'changed' => false,
                'revision' => $revision,
                'server_time' => now()->toIso8601String(),
                'matches' => [],
            ]);
        }

        return $this->ok([
            'changed' => true,
            'revision' => $revision,
            'server_time' => now()->toIso8601String(),
            'live_count' => $matches->where('status', 'live')->count(),
            'matches' => $matches->map(function (FixtureMatch $match) {
                return [
                    ...$this->matchPayload($match),
                    'updated_at' => $match->updated_at?->toIso8601String(),
                ];
            })->values(),
        ]);
    }

    /**
     * Unread notifications + latest items for badge / inbox auto-refresh.
     */
    public function notifications(Request $request): JsonResponse
    {
        $user = $request->user();

        $items = AppNotification::query()
            ->with(['recipients' => fn ($query) => $query->where('user_id', $user->id)])
            ->where('status', 'sent')
            ->where(function ($query) use ($user) {
                $query
                    ->whereIn('audience', ['public', 'all'])
                    ->orWhereHas('recipients', fn ($query) => $query->where('user_id', $user->id));
            })
            ->latest('sent_at')
            ->limit(20)
            ->get();

        $payload = $items->map(function (AppNotification $notification) {
            $mine = $notification->recipients->first();

            return [
                'id' => $notification->id,
                'title' => $notification->title,
                'body' => $notification->body,
                'channel' => $notification->channelLabel(),
                'sent_at' => $notification->sent_at?->toIso8601String(),
                'read' => $mine?->status === 'read',
            ];
        });

        $unread = $payload->where('read', false)->count();
        $latestAt = optional($items->first()?->sent_at)->timestamp ?? 0;

        return $this->ok([
            'unread' => $unread,
            'latest_at' => $latestAt,
            'items' => $payload->values(),
        ]);
    }

    public function markNotificationRead(Request $request, AppNotification $notification): JsonResponse
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

        return $this->ok(['read' => true]);
    }
}
