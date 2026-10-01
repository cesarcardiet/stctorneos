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
                class="ws-form"
                method="post"
                action="{{ $clubStoreUrl }}"
                enctype="multipart/form-data"
                data-ws-known-logo-url="{{ route('workspace.clubs.known-logo') }}"
            >
                @csrf
                <div class="ws-team-identity">
                    <x-ws-shield-field compact />
                    <div class="ws-setup-grid">
                        <label>Nombre
                            <input type="text" name="name" value="{{ old('name') }}" placeholder="Nombre del club, ej: San Lorenzo" required>
                        </label>
                        <label>País
                            <select name="country_code">
                                <option value="">Elegí un país</option>
                                @foreach (\App\Support\Countries::grouped() as $region => $codes)
                                    <optgroup label="{{ $region }}">
                                        @foreach ($codes as $code)
                                            <option value="{{ $code }}" @selected(old('country_code', 'AR') === $code)>{{ \App\Support\Countries::emoji($code) }} {{ \App\Support\Countries::name($code) }}</option>
                                        @endforeach
                                    </optgroup>
                                @endforeach
                            </select>
                        </label>
                        <label>Ciudad
                            <input type="text" name="city" value="{{ old('city') }}" placeholder="Ej: Buenos Aires">
                        </label>
                    </div>
                </div>
                <p class="stc-eyebrow">Delegado</p>
                <p class="ws-muted">Opcional. Si lo cargás ahora, queda asignado al club y se arma el acceso por WhatsApp.</p>
                <div class="ws-setup-grid">
                    <label>Nombre y apellido
                        <input type="text" name="delegate_name" value="{{ old('delegate_name') }}" placeholder="Ej: Ana Delegada">
                    </label>
                    <label>Correo
                        <input type="email" name="delegate_email" value="{{ old('delegate_email') }}" placeholder="ana@club.com">
                    </label>
                    <label>Teléfono (WhatsApp)
                        <input type="text" name="delegate_phone" value="{{ old('delegate_phone') }}" placeholder="+54 9 11 ...">
                    </label>
                </div>
                <p class="stc-eyebrow">Categorías</p>
                @if ($categories->isEmpty())
                    <p class="ws-muted">Este torneo todavía no tiene categorías. Creálas en el torneo y después elegilas acá.</p>
                @else
                    @php
                        $selectedCategories = collect(old('category_ids', $category ? [$category->id] : []))
                            ->map(fn ($id) => (int) $id);
                    @endphp
                    <p class="ws-muted">Elegí una o varias categorías ya creadas. El club queda con un equipo en cada una.</p>
                    <div class="ws-cat-checks">
                        @foreach ($categories as $item)
                            <label class="ws-cat-check">
                                <input type="checkbox" name="category_ids[]" value="{{ $item->id }}" @checked($selectedCategories->contains((int) $item->id))>
                                <span>{{ $item->name }}</span>
                            </label>
                        @endforeach
                    </div>
                    <label>Nombre del equipo
                        <input type="text" name="team_name" value="{{ old('team_name') }}" placeholder="Si lo dejás vacío, usa el nombre del club">
                    </label>
                @endif
                <div class="ws-form-actions">
                    <button type="submit" class="ws-btn">Añadir club</button>
                </div>
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
            <p class="ws-muted">Primero creá una categoría en este torneo. Después la elegís al añadir el club.</p>
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
