<?php

namespace App\Http\Controllers\Api;

use App\Models\AppNotification;
use App\Models\ContentPost;
use App\Models\FavoriteMatch;
use App\Models\FavoriteTeam;
use App\Models\FixtureMatch;
use App\Models\Team;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FeedController extends ApiController
{
    public function content(Request $request): JsonResponse
    {
        $posts = ContentPost::query()
            ->where('status', 'published')
            ->whereIn('audience', ['public', 'all'])
            ->when($request->filled('tournament_id'), fn ($query) => $query->where('tournament_id', $request->integer('tournament_id')))
            ->when($request->filled('type') && $request->string('type')->toString() !== 'all', fn ($query) => $query->where('type', $request->string('type')->toString()))
            ->orderByDesc('pinned')
            ->latest('published_at')
            ->get()
            ->map(fn (ContentPost $post) => $this->postPayload($post));

        return $this->ok($posts->values());
    }

    public function showContent(ContentPost $post): JsonResponse
    {
        abort_unless($post->status === 'published', 404);

        return $this->ok($this->postPayload($post));
    }

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
            ->get()
            ->map(function (AppNotification $notification) {
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

        return $this->ok($items->values());
    }

    public function favorites(Request $request): JsonResponse
    {
        $user = $request->user()->load(['favoriteTeams.team.category', 'favoriteMatches.match.homeTeam', 'favoriteMatches.match.awayTeam', 'favoriteMatches.match.category']);

        return $this->ok([
            'teams' => $user->favoriteTeams->map(fn (FavoriteTeam $favorite) => $this->teamPayload($favorite->team))->values(),
            'matches' => $user->favoriteMatches
                ->filter(fn (FavoriteMatch $favorite) => $favorite->match)
                ->map(fn (FavoriteMatch $favorite) => $this->matchPayload($favorite->match))
                ->values(),
        ]);
    }

    public function toggleTeam(Request $request, Team $team): JsonResponse
    {
        $existing = FavoriteTeam::query()
            ->where('user_id', $request->user()->id)
            ->where('team_id', $team->id)
            ->first();

        if ($existing) {
            $existing->delete();

            return $this->ok(['favorited' => false, 'team' => $this->teamPayload($team)], 'Equipo quitado de favoritos.');
        }

        FavoriteTeam::create([
            'user_id' => $request->user()->id,
            'team_id' => $team->id,
        ]);

        return $this->ok(['favorited' => true, 'team' => $this->teamPayload($team)], 'Equipo agregado a favoritos.');
    }

    public function toggleMatch(Request $request, FixtureMatch $match): JsonResponse
    {
        $existing = FavoriteMatch::query()
            ->where('user_id', $request->user()->id)
            ->where('match_id', $match->id)
            ->first();

        if ($existing) {
            $existing->delete();

            return $this->ok(['favorited' => false, 'match' => $this->matchPayload($match->loadMissing(['homeTeam', 'awayTeam', 'category', 'field']))], 'Partido quitado de favoritos.');
        }

        FavoriteMatch::create([
            'user_id' => $request->user()->id,
            'match_id' => $match->id,
        ]);

        return $this->ok(['favorited' => true, 'match' => $this->matchPayload($match->loadMissing(['homeTeam', 'awayTeam', 'category', 'field']))], 'Partido agregado a favoritos.');
    }
}
