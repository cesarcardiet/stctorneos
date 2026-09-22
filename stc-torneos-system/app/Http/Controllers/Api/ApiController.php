<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\ContentPost;
use App\Models\FixtureMatch;
use App\Models\Player;
use App\Models\Team;
use App\Models\Tournament;
use App\Models\User;
use Illuminate\Http\JsonResponse;

class ApiController extends Controller
{
    protected function ok(mixed $data = null, ?string $message = null, int $status = 200): JsonResponse
    {
        $payload = ['ok' => true];

        if ($message !== null) {
            $payload['message'] = $message;
        }

        if ($data !== null) {
            $payload['data'] = $data;
        }

        return response()->json($payload, $status);
    }

    protected function fail(string $message, int $status = 422, ?array $errors = null): JsonResponse
    {
        $payload = ['ok' => false, 'message' => $message];

        if ($errors !== null) {
            $payload['errors'] = $errors;
        }

        return response()->json($payload, $status);
    }

    protected function media(?string $path): ?string
    {
        if (! filled($path)) {
            return null;
        }

        return str_starts_with($path, 'http') ? $path : url($path);
    }

    /**
     * @return array<string, mixed>
     */
    protected function userPayload(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'role' => $user->roleLabel(),
            'status' => $user->status,
            'scope' => $user->current_scope,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function tournamentPayload(Tournament $tournament): array
    {
        return [
            'id' => $tournament->id,
            'name' => $tournament->name,
            'edition' => $tournament->edition,
            'slug' => $tournament->slug,
            'city' => $tournament->city,
            'country' => $tournament->country,
            'location' => $tournament->location,
            'status' => $tournament->status,
            'starts_at' => $tournament->starts_at?->toDateString(),
            'ends_at' => $tournament->ends_at?->toDateString(),
            'logo' => $tournament->logoUrl(),
            'visibility' => $tournament->visibility,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function categoryPayload(Category $category): array
    {
        return [
            'id' => $category->id,
            'name' => $category->name,
            'birth_year' => $category->birth_year,
            'branch' => $category->branch,
            'modality' => $category->modality,
            'format' => $category->competition_format,
            'image' => $category->bannerUrl(),
            'tournament' => $category->relationLoaded('tournament') && $category->tournament
                ? $this->tournamentPayload($category->tournament)
                : null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function teamPayload(Team $team): array
    {
        return [
            'id' => $team->id,
            'name' => $team->name,
            'city' => $team->city,
            'group' => $team->group_name,
            'shield' => $team->shieldUrl(),
            'country' => $team->countryName() ?: null,
            'country_code' => $team->resolvedCountryCode(),
            'flag' => $team->flagUrl(),
            'status' => $team->status,
            'category' => $team->category?->name,
            'delegation' => $team->delegation?->name ?: $team->delegation_name,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function matchPayload(FixtureMatch $match): array
    {
        return [
            'id' => $match->id,
            'title' => $match->title(),
            'stage' => $match->stage,
            'round' => $match->round,
            'status' => $match->status,
            'status_label' => $match->statusLabel(),
            'score' => $match->scoreLine(),
            'home_score' => $match->home_score,
            'away_score' => $match->away_score,
            'minute' => $match->minute,
            'scheduled_at' => $match->scheduled_at?->toIso8601String(),
            'field' => $match->field?->name,
            'category' => $match->category?->name,
            'tournament' => $match->category?->tournament?->name,
            'published' => (bool) $match->published,
            'home' => $match->homeTeam ? $this->teamPayload($match->homeTeam) : null,
            'away' => $match->awayTeam ? $this->teamPayload($match->awayTeam) : null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function playerPayload(Player $player): array
    {
        return [
            'id' => $player->id,
            'name' => $player->fullName(),
            'position' => $player->position,
            'jersey' => $player->jersey_number,
            'kit_size' => $player->kit_size,
            'photo' => $player->photoUrl(),
            'status' => $player->eligibilityLabel(),
            'team' => $player->team ? $this->teamPayload($player->team) : null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function postPayload(ContentPost $post): array
    {
        return [
            'id' => $post->id,
            'slug' => $post->slug,
            'type' => $post->type,
            'type_label' => $post->typeLabel(),
            'title' => $post->title,
            'summary' => $post->summary,
            'body' => $post->body,
            'cover' => $this->media($post->cover_path),
            'audience' => $post->audience,
            'pinned' => (bool) $post->pinned,
            'published_at' => $post->published_at?->toIso8601String(),
        ];
    }
}
