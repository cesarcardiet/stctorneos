<?php

namespace App\Models;

use App\Support\Countries;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

class Team extends Model
{
    protected $fillable = [
        'tournament_id',
        'category_id',
        'delegation_id',
        'name',
        'delegation_name',
        'city',
        'country_code',
        'group_name',
        'home_kit',
        'away_kit',
        'player_capacity',
        'shield_path',
        'notes',
        'status',
        'roster_open',
    ];

    protected function casts(): array
    {
        return [
            'roster_open' => 'boolean',
        ];
    }

    public function tournament(): BelongsTo
    {
        return $this->belongsTo(Tournament::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function delegation(): BelongsTo
    {
        return $this->belongsTo(Delegation::class);
    }

    public function homeMatches(): HasMany
    {
        return $this->hasMany(FixtureMatch::class, 'home_team_id');
    }

    public function awayMatches(): HasMany
    {
        return $this->hasMany(FixtureMatch::class, 'away_team_id');
    }

    public function players(): HasMany
    {
        return $this->hasMany(Player::class);
    }

    public function staffMembers(): HasMany
    {
        return $this->hasMany(TeamStaff::class);
    }

    public function scopeAccessibleTo(Builder $query, User $user): void
    {
        if ($user->canAccessAllTournaments()) {
            return;
        }

        $clubIds = $user->scopedDelegationIds();
        if ($clubIds !== []) {
            $query->whereIn('delegation_id', $clubIds);

            return;
        }

        $query->whereIn('tournament_id', $user->assignedTournamentIds() ?: [0]);
    }

    /**
     * @return array<string, string>
     */
    public static function statusLabels(): array
    {
        return [
            'approved' => 'Habilitado',
            'pending' => 'Pendiente',
            'observed' => 'En revisión',
            'blocked' => 'Bloqueado',
        ];
    }

    public function statusLabel(): string
    {
        return self::statusLabels()[$this->status] ?? ucfirst((string) $this->status);
    }

    public function isRosterOpen(): bool
    {
        return $this->roster_open !== false;
    }

    public function registrationPeriodClosed(): bool
    {
        $this->loadMissing('category');
        if ($this->category?->registrationsOpen()) {
            return false;
        }

        $ends = $this->tournament?->registration_ends_at;

        return $ends !== null && $ends->copy()->endOfDay()->isPast();
    }

    public function hasOwnShield(): bool
    {
        return Delegation::pathIsUsable($this->shield_path);
    }

    public function shieldUrl(): string
    {
        if ($this->hasOwnShield()) {
            $path = $this->shield_path;

            return str_starts_with($path, 'http') ? $path : asset($path);
        }

        $this->loadMissing('delegation');
        if ($this->delegation?->hasCustomLogo()) {
            return $this->delegation->logoUrl();
        }

        return asset('images/stc-logo.png');
    }

    public function attachClub(?int $delegationId = null, ?string $name = null): Delegation
    {
        $country = Countries::name($this->country_code)
            ?: ($this->relationLoaded('delegation') ? $this->delegation?->country : null)
            ?: 'Argentina';

        $delegationId = $delegationId ?: $this->delegation_id;
        $club = null;

        if ($delegationId) {
            $club = Delegation::query()
                ->where('tournament_id', $this->tournament_id)
                ->find($delegationId);
        }

        if (! $club) {
            $club = Delegation::resolveForClub(
                (int) $this->tournament_id,
                $name ?: $this->delegation_name ?: $this->name ?: 'Sin delegación',
                $country
            );
        }

        $switching = $this->exists
            && $this->delegation_id
            && (int) $this->delegation_id !== (int) $club->id;

        $this->fill([
            'delegation_id' => $club->id,
            'delegation_name' => $club->name,
        ]);

        if ($switching) {
            $this->shield_path = $club->hasCustomLogo() ? $club->logo_path : null;
        } elseif (! $this->hasOwnShield() && $club->hasCustomLogo()) {
            $this->shield_path = $club->logo_path;
        }

        if ($this->exists) {
            $this->save();
        }

        $this->setRelation('delegation', $club);

        if (! $club->hasCustomLogo() && $this->hasOwnShield()) {
            $club->adoptExistingLogo($this->shield_path);
        }

        $club->claimOrphanTeams();

        return $club;
    }

    public function storeClubShield(string $binary, string $ext = 'png'): bool
    {
        $club = $this->attachClub();
        $ok = $club->storeLogo($binary, $ext);
        if ($ok) {
            $this->refresh();
            $this->setRelation('delegation', $club->fresh());
        }

        return $ok;
    }

    public function resolvedCountryCode(): ?string
    {
        $code = Countries::guessCode($this->country_code);
        if ($code) {
            return $code;
        }

        return Countries::guessCode(
            $this->relationLoaded('delegation') ? $this->delegation?->country : null,
            $this->delegation_name,
            $this->relationLoaded('tournament') ? $this->tournament?->country : null,
        );
    }

    public function countryName(): string
    {
        return Countries::name($this->resolvedCountryCode());
    }

    public function flagUrl(): ?string
    {
        return Countries::flagUrl($this->resolvedCountryCode());
    }

    public function matchesQuery(): Builder
    {
        return FixtureMatch::query()
            ->where(fn (Builder $query) => $query
                ->where('home_team_id', $this->id)
                ->orWhere('away_team_id', $this->id));
    }

    /**
     * @return Collection<int, FixtureMatch>
     */
    public function allMatches(): Collection
    {
        return $this->matchesQuery()
            ->with(['homeTeam', 'awayTeam', 'field', 'category'])
            ->orderBy('scheduled_at')
            ->get();
    }

    /**
     * @return array{played: int, won: int, drawn: int, lost: int, gf: int, ga: int}
     */
    public function matchStats(): array
    {
        $finished = $this->allMatches()->whereIn('status', ['finished', 'validated']);

        $stats = ['played' => $finished->count(), 'won' => 0, 'drawn' => 0, 'lost' => 0, 'gf' => 0, 'ga' => 0];

        foreach ($finished as $match) {
            $isHome = (int) $match->home_team_id === (int) $this->id;
            $gf = (int) ($isHome ? $match->home_score : $match->away_score);
            $ga = (int) ($isHome ? $match->away_score : $match->home_score);
            $stats['gf'] += $gf;
            $stats['ga'] += $ga;

            if ($gf > $ga) {
                $stats['won']++;
            } elseif ($gf === $ga) {
                $stats['drawn']++;
            } else {
                $stats['lost']++;
            }
        }

        return $stats;
    }
}
