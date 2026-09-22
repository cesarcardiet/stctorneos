@php
    $categories = $categories ?? collect();
    $canManageClubs = $canManageClubs ?? $canEdit;
    $clubStoreUrl = $category
        ? route('workspace.categories.clubs.store', $category)
        : route('workspace.tournaments.clubs.store', $tournament);
    $clubShow = fn ($club) => $category
        ? route('workspace.categories.clubs.show', [$category, $club])
        : route('workspace.tournaments.clubs.show', [$tournament, $club]);
    $clubDestroy = fn ($club) => $category
        ? route('workspace.categories.clubs.destroy', [$category, $club])
        : route('workspace.tournaments.clubs.destroy', [$tournament, $club]);
@endphp

<x-layouts.workspace
    :title="$title"
    heading="Delegaciones"
    :subheading="$tournament->name"
    :category="$category"
    :tournament="$tournament"
    active="Delegaciones"
>
    <p class="ws-back">
        @if ($category)
            <a href="{{ route('workspace.categories.settings', $category) }}">← Configuración</a>
        @else
            <a href="{{ route('workspace.tournaments.show', $tournament) }}">← Categorías</a>
        @endif
    </p>

    @if ($canManageClubs)
        <article class="ws-card">
            <p class="stc-eyebrow">Nueva delegación / club</p>
            <p class="ws-muted">El club es del torneo. Los equipos de todas las categorías quedan vinculados a esta delegación.</p>
            <form
                class="ws-inline-add ws-club-add"
                method="post"
                action="{{ $clubStoreUrl }}"
                enctype="multipart/form-data"
                data-ws-known-logo-url="{{ route('workspace.clubs.known-logo') }}"
            >
                @csrf
                <input type="text" name="name" placeholder="Nombre del club, ej: San Lorenzo" required>
                <select name="country_code">
                    <option value="">País</option>
                    @foreach (\App\Support\Countries::grouped() as $region => $codes)
                        <optgroup label="{{ $region }}">
                            @foreach ($codes as $code)
                                <option value="{{ $code }}" @selected($code === 'AR')>{{ \App\Support\Countries::emoji($code) }} {{ \App\Support\Countries::name($code) }}</option>
                            @endforeach
                        </optgroup>
                    @endforeach
                </select>
                <button type="submit" class="ws-btn">Añadir club</button>
                <x-ws-shield-field compact class="ws-team-add-shield" />
            </form>
        </article>
        @if ($clubs->isNotEmpty() && $categories->isNotEmpty())
            <article class="ws-card">
                <p class="stc-eyebrow">Equipo en una categoría</p>
                <p class="ws-muted">El mismo club puede tener un equipo en cada categoría. Varios clubes pueden jugar en la misma.</p>
                <form class="ws-inline-add ws-club-team-add" method="post" action="{{ route('workspace.tournaments.teams.store', $tournament) }}">
                    @csrf
                    <select name="delegation_id" required>
                        <option value="">Club</option>
                        @foreach ($clubs as $club)
                            <option value="{{ $club->id }}" @selected((int) old('delegation_id') === (int) $club->id)>{{ $club->name }}</option>
                        @endforeach
                    </select>
                    <select name="category_id" required>
                        <option value="">Categoría</option>
                        @foreach ($categories as $item)
                            <option value="{{ $item->id }}" @selected((int) old('category_id', $category?->id) === (int) $item->id)>{{ $item->name }}</option>
                        @endforeach
                    </select>
                    <input type="text" name="name" value="{{ old('name') }}" placeholder="Nombre del equipo (si no, usa el del club)">
                    <button type="submit" class="ws-btn">Añadir equipo</button>
                </form>
            </article>
        @elseif ($categories->isEmpty())
            <p class="ws-muted">Primero creá una categoría en este torneo para poder sumarle equipos.</p>
        @else
            <p class="ws-muted">Primero añadí un club. Después le sumás el equipo a la categoría.</p>
        @endif
    @endif

    <p class="ws-muted">Total: {{ $clubs->count() }}</p>

    <div data-live-filter>
        @include('workspace.partials.live-search', [
            'placeholder' => 'Buscar club, ciudad o delegado…',
            'empty' => 'No hay delegaciones con esa búsqueda.',
        ])
        <div class="ws-list">
            @forelse ($clubs as $club)
                <div
                    class="ws-list-row"
                    data-live-item
                    data-search="{{ $club->name }} {{ $club->originLabel() }} {{ $club->responsibleLabel() }} {{ $club->city }} {{ $club->country }}"
                >
                <img class="ws-team-shield" src="{{ $club->logoUrl() }}" alt="">
                <a href="{{ $clubShow($club) }}">
                    <strong>{{ strtoupper($club->name) }}</strong>
                    <span>{{ $club->originLabel() }} · {{ $club->teams_count }} equipos · {{ $club->responsibleLabel() }}</span>
                </a>
                @if ($canEdit)
                    <span class="ws-row-actions">
                        <a href="{{ $clubShow($club) }}">Editar</a>
                        @if ($canManageClubs)
                            <form method="post" action="{{ $clubDestroy($club) }}" data-confirm="¿Eliminar {{ $club->name }}? Los equipos quedan, sin esta delegación.">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="is-danger">Eliminar</button>
                            </form>
                        @endif
                    </span>
                @endif
            </div>
        @empty
            <div class="ws-empty">Todavía no hay clubes en este torneo.</div>
        @endforelse
        </div>
    </div>
</x-layouts.workspace>
