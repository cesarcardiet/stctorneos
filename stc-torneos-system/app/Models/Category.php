<?php

namespace App\Models;

use App\Support\CategoryWorkspace;
use App\Support\ShieldPayload;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class Category extends Model
{
    public const MAX_GROUPS = 32;

    protected $fillable = [
        'tournament_id',
        'name',
        'birth_year',
        'branch',
        'modality',
        'format',
        'image_path',
        'team_limit',
        'min_players',
        'max_players',
        'players_on_field',
        'substitutes',
        'periods',
        'period_duration',
        'competition_format',
        'groups_count',
        'qualifiers_count',
        'points_win',
        'points_draw',
        'points_loss',
        'tiebreakers',
        'rules',
        'discipline_rules',
        'fair_play_yellow',
        'fair_play_red',
        'fair_play_incident',
        'status',
        'sort_order',
        'custom_modality',
        'teams_per_group',
        'phases',
        'brackets',
        'classification_criteria',
        'workspace_config',
    ];

    protected function casts(): array
    {
        return [
            'tiebreakers' => 'array',
            'workspace_config' => 'array',
            'fair_play_yellow' => 'integer',
            'fair_play_red' => 'integer',
            'fair_play_incident' => 'integer',
        ];
    }

    /**
     * @return array{yellow: int, red: int, incident: int}
     */
    public function fairPlayWeights(): array
    {
        return [
            'yellow' => max(0, (int) ($this->fair_play_yellow ?? 1)),
            'red' => max(0, (int) ($this->fair_play_red ?? 3)),
            'incident' => max(0, (int) ($this->fair_play_incident ?? 2)),
        ];
    }

    public function tournament(): BelongsTo
    {
        return $this->belongsTo(Tournament::class);
    }

    public function teams(): HasMany
    {
        return $this->hasMany(Team::class);
    }

    public function matches(): HasMany
    {
        return $this->hasMany(FixtureMatch::class);
    }

    public function polls(): HasMany
    {
        return $this->hasMany(CategoryPoll::class)->orderBy('sort_order')->orderBy('id');
    }

    public function players(): HasManyThrough
    {
        return $this->hasManyThrough(Player::class, Team::class);
    }

    public function scopeAccessibleTo(Builder $query, User $user): void
    {
        if ($user->canAccessAllTournaments()) {
            return;
        }

        $clubIds = $user->scopedDelegationIds();
        if ($clubIds !== []) {
            $query->whereHas('teams', fn (Builder $teams) => $teams->whereIn('delegation_id', $clubIds));

            return;
        }

        $query->whereIn('tournament_id', $user->assignedTournamentIds() ?: [0]);
    }

    /**
     * Listado de consulta pública: delegados ven todas las categorías del torneo/sistema.
     */
    public function scopeForPublicBrowse(Builder $query, User $user, ?int $tournamentId = null): void
    {
        if ($tournamentId !== null) {
            $query->where('tournament_id', $tournamentId);
        }

        if ($user->restrictsToAssignedClub()) {
            return;
        }

        if (! $user->canAccessAllTournaments()) {
            $query->whereIn('tournament_id', $user->assignedTournamentIds() ?: [0]);
        }
    }

    /**
     * @return list<string>
     */
    public static function modalities(): array
    {
        return ['Fútbol 5', 'Fútbol 6', 'Fútbol 7', 'Fútbol 8', 'Fútbol 9', 'Fútbol 10', 'Fútbol 11', 'Personalizada'];
    }

    /**
     * @return list<string>
     */
    public static function competitionFormats(): array
    {
        return [
            'Todos contra todos',
            'Grupos y finales',
            'Grupos, semifinales y final',
            'Eliminación directa',
            'Liga',
            'Interzonales',
            'Grupos y llaves',
            'Copa Oro',
            'Copa Plata',
            'Copa Bronce',
            'Copa Amistad',
            'Formato personalizado',
        ];
    }

    /**
     * Formatos que requieren definir cantidad de grupos / zonas.
     *
     * @return list<string>
     */
    public static function groupCompetitionFormats(): array
    {
        return [
            'Grupos y finales',
            'Grupos, semifinales y final',
            'Interzonales',
            'Grupos y llaves',
            'Copa Oro',
            'Copa Plata',
            'Copa Bronce',
            'Copa Amistad',
            'Formato personalizado',
        ];
    }

    public static function formatNeedsGroups(?string $format): bool
    {
        $format = trim((string) $format);

        return $format !== '' && in_array($format, self::groupCompetitionFormats(), true);
    }

    public static function resolveCompetitionFormat(?string $format, ?string $custom = null): string
    {
        $format = trim((string) $format);

        if ($format === 'Formato personalizado') {
            return trim((string) $custom);
        }

        return $format;
    }

    /**
     * @return array<string, string>
     */
    public static function statusLabels(): array
    {
        return [
            'active' => 'Activa',
            'inactive' => 'Inactiva',
            'draft' => 'Borrador',
        ];
    }

    public function statusLabel(): string
    {
        return self::statusLabels()[$this->status] ?? ucfirst((string) $this->status);
    }

    public function modalityLabel(): string
    {
        if ($this->modality === 'Personalizada' && filled($this->custom_modality)) {
            return $this->custom_modality;
        }

        return (string) $this->modality;
    }

    public function sortLetter(): string
    {
        return self::letterFromName((string) $this->name);
    }

    public static function letterFromName(string $name): string
    {
        $first = mb_substr(trim($name), 0, 1);
        $folded = strtoupper(Str::ascii($first));
        $letter = $folded[0] ?? '';

        return ctype_alpha($letter) ? $letter : '#';
    }

    public function searchHaystack(): string
    {
        return trim(implode(' ', [
            $this->name,
            $this->birth_year,
            $this->branch,
            $this->modalityLabel(),
            $this->competition_format,
        ]));
    }

    public static function identityKeyFrom(string $name, ?string $birthYear, ?string $branch): string
    {
        return mb_strtolower(trim($name)).'|'.mb_strtolower(trim((string) $birthYear)).'|'.mb_strtolower(trim((string) $branch));
    }

    public function identityKey(): string
    {
        return self::identityKeyFrom((string) $this->name, $this->birth_year, $this->branch);
    }

    public function siblings(): Builder
    {
        return static::query()
            ->where('id', '!=', $this->id)
            ->where('name', $this->name)
            ->where('birth_year', $this->birth_year)
            ->where('branch', $this->branch);
    }

    public function syncBannerToSiblings(): void
    {
        $this->siblings()->update(['image_path' => $this->image_path]);
    }

    public static function inheritedBannerPath(string $name, ?string $birthYear, ?string $branch): ?string
    {
        $path = static::query()
            ->where('name', $name)
            ->where('birth_year', $birthYear)
            ->where('branch', $branch)
            ->whereNotNull('image_path')
            ->where('image_path', '!=', '')
            ->whereNotIn('image_path', ['images/stc-logo.png', 'images/category-banner.svg'])
            ->orderByDesc('updated_at')
            ->value('image_path');

        return is_string($path) && $path !== '' ? $path : null;
    }

    /**
     * @return array<string, mixed>
     */
    public function workspace(): array
    {
        return array_replace_recursive(CategoryWorkspace::defaults(), $this->workspace_config ?? []);
    }

    public function workspaceValue(string $key, mixed $default = null): mixed
    {
        return data_get($this->workspace(), $key, $default);
    }

    /**
     * @param  array<string, mixed>  $patch
     */
    public function mergeWorkspace(array $patch): void
    {
        $this->workspace_config = array_replace_recursive($this->workspace(), $patch);
        $this->save();
    }

    public function bannerUrl(): string
    {
        if ($this->hasCustomBanner()) {
            $path = (string) $this->image_path;

            return str_starts_with($path, 'http') ? $path : asset($path);
        }

        $this->loadMissing('tournament');

        return $this->tournament?->logoUrl() ?: asset('images/stc-logo.png');
    }

    public function hasCustomBanner(): bool
    {
        $path = trim((string) $this->image_path);
        if ($path === '' || in_array($path, ['images/stc-logo.png', 'images/category-banner.svg'], true)) {
            return false;
        }

        $this->loadMissing('tournament');
        $tournamentLogo = trim((string) ($this->tournament?->resolvedLogoPath() ?? ''));
        if ($tournamentLogo !== '' && $path === $tournamentLogo) {
            return false;
        }

        if (str_starts_with($path, 'http')) {
            return true;
        }

        return is_file(public_path($path));
    }

    /**
     * @return list<array{name: string, path: string, url: string}>
     */
    public static function bannerLogoChoicesForTournament(int $tournamentId): array
    {
        $tournament = $tournamentId > 0 ? Tournament::query()->find($tournamentId) : null;
        $tournamentPath = $tournament?->resolvedLogoPath() ?: 'images/stc-logo.png';
        $choices = [[
            'name' => $tournament?->name ?: 'Torneo',
            'path' => $tournamentPath,
            'url' => $tournament?->logoUrl() ?? asset('images/stc-logo.png'),
        ]];
        $seen = [$tournamentPath => true];

        if (! isset($seen['images/stc-logo.png'])) {
            $choices[] = [
                'name' => 'STC',
                'path' => 'images/stc-logo.png',
                'url' => asset('images/stc-logo.png'),
            ];
            $seen['images/stc-logo.png'] = true;
        }

        $clubs = Delegation::query()
            ->where('tournament_id', $tournamentId)
            ->orderBy('name')
            ->get();

        foreach ($clubs as $club) {
            $path = trim((string) $club->logo_path);
            if ($path === '' || isset($seen[$path]) || ! Delegation::pathIsUsable($path)) {
                continue;
            }

            $seen[$path] = true;
            $choices[] = [
                'name' => $club->name,
                'path' => $path,
                'url' => $club->logoUrl(),
            ];
        }

        return $choices;
    }

    /**
     * @return list<array{name: string, path: string, url: string}>
     */
    public function bannerLogoChoices(): array
    {
        return self::bannerLogoChoicesForTournament((int) $this->tournament_id);
    }

    public function applyBannerFromRequest(Request $request): bool
    {
        $payload = ShieldPayload::fromRequest($request)
            ?? ShieldPayload::fromRequest($request, 'image_file');

        if ($payload) {
            return $this->storeBanner($payload[0], $payload[1]);
        }

        $path = trim((string) $request->input('image_path', ''));
        if ($path === '') {
            return false;
        }

        return $this->adoptBannerPath($path);
    }

    public function storeBanner(string $binary, string $ext = 'png'): bool
    {
        if (strlen($binary) < 24) {
            return false;
        }

        if (! in_array($ext, ['png', 'jpg', 'webp', 'gif'], true)) {
            $ext = 'png';
        }

        $directory = public_path('images/categories');
        File::ensureDirectoryExists($directory);
        $filename = Str::slug($this->name ?: 'categoria').'-'.Str::random(8).'.'.$ext;
        File::put($directory.DIRECTORY_SEPARATOR.$filename, $binary);

        $old = $this->image_path;
        $path = 'images/categories/'.$filename;
        $this->update(['image_path' => $path]);
        $this->syncBannerToSiblings();
        $this->deleteUnusedBanner($old);

        return true;
    }

    public function adoptBannerPath(?string $path): bool
    {
        $path = trim((string) $path);
        if ($path === '' || str_contains($path, '..') || str_contains($path, "\0")) {
            return false;
        }

        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
            $ok = Delegation::query()
                ->where('tournament_id', $this->tournament_id)
                ->where('logo_path', $path)
                ->exists();
            if (! $ok) {
                return false;
            }

            $old = $this->image_path;
            $this->update(['image_path' => $path]);
            $this->syncBannerToSiblings();
            $this->deleteUnusedBanner($old);

            return true;
        }

        $path = ltrim(str_replace('\\', '/', $path), '/');
        $allowedExact = ['images/stc-logo.png', 'images/category-banner.svg'];
        $inFolder = str_starts_with($path, 'images/categories/')
            || str_starts_with($path, 'images/delegations/')
            || str_starts_with($path, 'images/teams/')
            || str_starts_with($path, 'images/tournaments/');

        if (! in_array($path, $allowedExact, true) && ! $inFolder) {
            return false;
        }

        if ($inFolder && ! in_array($path, $allowedExact, true)) {
            $owned = str_starts_with($path, 'images/categories/')
                || str_starts_with($path, 'images/tournaments/')
                || Delegation::query()->where('tournament_id', $this->tournament_id)->where('logo_path', $path)->exists()
                || Team::query()->where('tournament_id', $this->tournament_id)->where('shield_path', $path)->exists();
            if (! $owned) {
                return false;
            }
        }

        if (! is_file(public_path($path))) {
            return false;
        }

        $old = $this->image_path;
        $this->update(['image_path' => $path]);
        $this->syncBannerToSiblings();
        $this->deleteUnusedBanner($old);

        return true;
    }

    private function deleteUnusedBanner(?string $path): void
    {
        $path = trim((string) $path);
        if ($path === '' || str_starts_with($path, 'http') || ! str_starts_with($path, 'images/categories/')) {
            return;
        }

        if (in_array($path, ['images/category-banner.svg', 'images/stc-logo.png'], true)) {
            return;
        }

        if (static::query()->where('image_path', $path)->where('id', '!=', $this->id)->exists()) {
            return;
        }

        if (is_file(public_path($path))) {
            File::delete(public_path($path));
        }
    }

    public static function groupLetterAt(int $index): string
    {
        $n = max(0, $index) + 1;
        $letter = '';
        while ($n > 0) {
            $n--;
            $letter = chr(ord('A') + ($n % 26)).$letter;
            $n = intdiv($n, 26);
        }

        return $letter;
    }

    /**
     * @return list<string>
     */
    public static function groupAlphabet(int $count): array
    {
        $count = max(0, min(self::MAX_GROUPS, $count));
        $letters = [];
        for ($i = 0; $i < $count; $i++) {
            $letters[] = self::groupLetterAt($i);
        }

        return $letters;
    }

    /**
     * @return list<string>
     */
    public function groupLetters(): array
    {
        return static::groupAlphabet((int) $this->groups_count);
    }

    /**
     * @return list<string>
     */
    public function groupOptions(?string $current = null): array
    {
        $letters = $this->groupLetters();
        $current = strtoupper(trim(str_ireplace('grupo ', '', (string) $current)));
        if ($current !== '' && ! in_array($current, $letters, true)) {
            $letters[] = $current;
        }

        sort($letters, SORT_NATURAL | SORT_FLAG_CASE);

        return $letters;
    }

    public function groupCustomName(string $letter): string
    {
        $letter = strtoupper(trim(str_ireplace('grupo ', '', $letter)));
        $names = $this->workspaceValue('group_names', []);

        return trim((string) ($names[$letter] ?? ''));
    }

    public function groupDisplayName(?string $letter): string
    {
        $letter = strtoupper(trim(str_ireplace('grupo ', '', (string) $letter)));
        if ($letter === '' || $letter === '—') {
            return 'Sin grupo';
        }

        $custom = $this->groupCustomName($letter);

        return $custom !== '' ? $custom : 'Grupo '.$letter;
    }

    /**
     * @return list<string>
     */
    public function mixPairLetters(): array
    {
        $fromTeams = $this->relationLoaded('teams')
            ? $this->teams->pluck('group_name')
            : $this->teams()->pluck('group_name');

        $letters = array_merge(
            $this->groupLetters(),
            $fromTeams->map(fn ($name) => strtoupper(trim((string) $name)))->filter()->all()
        );
        $letters = array_values(array_unique(array_filter($letters)));
        sort($letters, SORT_NATURAL | SORT_FLAG_CASE);

        return $letters;
    }

    /**
     * Pares por defecto: A-B, C-D, E-F...
     *
     * @return array<string, string>
     */
    public function defaultMixPairs(): array
    {
        $letters = $this->mixPairLetters();
        $pairs = [];
        for ($i = 0; $i + 1 < count($letters); $i += 2) {
            $pairs[$letters[$i]] = $letters[$i + 1];
            $pairs[$letters[$i + 1]] = $letters[$i];
        }

        return $pairs;
    }

    public function needsGroupSetup(): bool
    {
        return self::formatNeedsGroups($this->competition_format)
            || (! in_array($this->competition_format, self::competitionFormats(), true)
                && (int) $this->groups_count > 0);
    }

    public function registrationsOpen(): bool
    {
        return (bool) $this->workspaceValue('registrations_open', true);
    }

    public function publicRegistrationLabel(): string
    {
        if (! $this->registrationsOpen()) {
            return 'Inscripciones cerradas';
        }

        return 'Inscripciones abiertas';
    }

    public function tournamentRegistrationWindowOpen(): bool
    {
        $this->loadMissing('tournament');
        $tournament = $this->tournament;

        if ($tournament?->registration_ends_at?->copy()->endOfDay()->isPast()) {
            return false;
        }

        if ($tournament?->registration_starts_at?->copy()->startOfDay()->isFuture()) {
            return false;
        }

        return true;
    }

    public function acceptsRosterEdits(?\App\Models\Team $team = null): bool
    {
        if (! $this->registrationsOpen()) {
            return false;
        }

        if ($team !== null && ! $team->isRosterOpen()) {
            return false;
        }

        return true;
    }

    public function rosterEditBlockedReason(?\App\Models\Team $team = null): string
    {
        if (! $this->registrationsOpen()) {
            $info = trim((string) $this->workspaceValue('registration_info', ''));

            return $info !== ''
                ? $info
                : 'Las inscripciones están cerradas. Solo la organización puede modificar planteles.';
        }

        if ($team !== null && ! $team->isRosterOpen()) {
            return 'La lista de buena fe de tu equipo está cerrada.';
        }

        return 'No podés modificar el plantel en este momento.';
    }
}
