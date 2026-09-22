@php
    use App\Support\WorkspaceContext;

    $crumbUser = auth()->user();
    $crumbTournament = $tournament ?? $category?->tournament ?? WorkspaceContext::activeTournament($crumbUser);
    $crumbCategory = $category ?? null;
    $crumbActive = $active ?? null;
    $crumbModule = match ($crumbActive) {
        'Torneos', 'Categorías', 'Delegaciones' => null,
        'Inicio' => $crumbCategory ? 'Inicio' : null,
        default => $crumbActive,
    };
@endphp

@if (($crumbActive ?? '') === 'Torneos' || $crumbTournament)
    <nav class="ws-crumb" aria-label="Ruta de navegación">
        <a href="{{ route('workspace.home') }}" @class(['is-current' => ($crumbActive ?? '') === 'Torneos' && ! $crumbCategory])>Torneos</a>

        @if ($crumbTournament)
            <span class="ws-crumb-sep" aria-hidden="true">›</span>
            <a
                href="{{ route('workspace.tournaments.show', $crumbTournament) }}"
                @class(['is-current' => ($crumbActive ?? '') === 'Categorías' && ! $crumbCategory])
            >{{ $crumbTournament->name }}</a>
        @endif

        @if ($crumbCategory)
            <span class="ws-crumb-sep" aria-hidden="true">›</span>
            <a
                href="{{ route('workspace.categories.home', $crumbCategory) }}"
                @class(['is-current' => ($crumbActive ?? '') === 'Inicio' && ! $crumbModule])
            >{{ $crumbCategory->name }}</a>
        @endif

        @if ($crumbModule)
            <span class="ws-crumb-sep" aria-hidden="true">›</span>
            <span class="is-current">{{ $crumbModule }}</span>
        @endif
    </nav>
@endif
