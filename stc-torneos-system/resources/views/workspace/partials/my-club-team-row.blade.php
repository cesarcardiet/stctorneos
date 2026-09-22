@php
    $category = $team->category;
    $canEditRoster = $category && $category->acceptsRosterEdits($team);
    $registrationLabel = $category?->publicRegistrationLabel() ?? 'Sin categoría';
    $teamUrl = $category
        ? route('workspace.categories.teams.show', [$category, $team])
        : null;
    $rosterUrl = $category && $canEditRoster
        ? route('workspace.categories.players', [$category, 'team_id' => $team->id])
        : null;
@endphp

<div class="ws-list-row @if($highlight ?? false) is-open @endif">
    <img class="ws-team-shield" src="{{ $team->shieldUrl() }}" alt="">
    <div>
        @if ($teamUrl)
            <a href="{{ $teamUrl }}">
                <strong>{{ strtoupper($team->name) }}</strong>
            </a>
        @else
            <strong>{{ strtoupper($team->name) }}</strong>
        @endif
        <span>
            {{ $category?->tournament?->name ?? '—' }}
            · {{ $category?->name ?? 'Sin categoría' }}
            @if ($team->group_name)
                · {{ $category?->groupDisplayName($team->group_name) ?? 'Grupo '.$team->group_name }}
            @endif
        </span>
        <small class="ws-reg-badge @if($canEditRoster) is-open @else is-closed @endif">{{ $registrationLabel }}</small>
    </div>
    <span class="ws-row-actions">
        @if ($rosterUrl)
            <a href="{{ $rosterUrl }}">Mi plantel</a>
        @elseif ($teamUrl)
            <a href="{{ $teamUrl }}">Ver</a>
        @endif
    </span>
</div>
