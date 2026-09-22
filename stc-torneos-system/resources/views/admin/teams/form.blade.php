@php
    $selectedDelegation = $delegations->firstWhere('id', (int) old('delegation_id', $team->delegation_id));
    $playersCount = (int) ($team->players_count ?? 0);
    $capacity = max(1, (int) old('player_capacity', $team->player_capacity ?: 14));
    $hasShield = $team->exists && ($team->hasOwnShield() || filled($team->shield_path) || filled($selectedDelegation?->logo_path));
    $rosterIncomplete = $team->exists && $playersCount < $capacity;
    $checks = [
        ['Delegación aprobada', (bool) $selectedDelegation && $selectedDelegation->status === 'approved', $selectedDelegation?->status === 'approved' ? 'ok' : 'warn'],
        ['Categoría disponible', (bool) old('category_id', $team->category_id), old('category_id', $team->category_id) ? 'ok' : 'warn'],
        ['Cupo dentro del límite', $playersCount <= $capacity, $playersCount <= $capacity ? 'ok' : 'warn'],
        ['Escudo con buena resolución', $hasShield, $hasShield ? 'ok' : 'warn'],
        [$rosterIncomplete ? 'Lista buena fe incompleta' : 'Lista de buena fe completa', ! $rosterIncomplete, $rosterIncomplete ? 'warn' : 'ok'],
    ];
@endphp

<x-layouts.stc
    :title="$title.' | STC Torneos'"
    active="Equipos"
    heading="Crear / Editar Equipo"
    subheading="Alta y mantenimiento de equipos por delegación, categoría y grupo competitivo."
>
    <a class="back-link" href="{{ route('admin.teams.index') }}">← Volver al listado</a>

    @if ($errors->any())
        <div class="system-alert">Revisá los campos marcados antes de guardar.</div>
    @endif

    <section class="ws-cat-edit admin-team-edit">
        <header class="ws-cat-edit-bar">
            <div>
                <strong>Crear / Editar Equipo</strong>
                <span>{{ $team->exists ? $team->name.' · '.($selectedDelegation?->name ?: $team->delegation_name) : 'Datos del plantel por delegación, categoría y grupo competitivo.' }}</span>
            </div>
            <button type="submit" form="team-profile" class="stc-btn-new">GUARDAR</button>
        </header>

        <form id="team-profile" class="ws-cat-edit-grid" action="{{ $url }}" method="post" enctype="multipart/form-data">
            @csrf
            @if ($method === 'PUT')
                @method('PUT')
            @endif

            <article class="ws-cat-edit-card">
                <div class="ws-cat-fields">
                    <label>Equipo
                        <input name="name" value="{{ old('name', $team->name) }}" placeholder="San Lorenzo 2014" required>
                    </label>
                    <label>Delegación
                        <select name="delegation_id">
                            <option value="">Sin delegación</option>
                            @foreach ($delegations as $delegation)
                                <option value="{{ $delegation->id }}" @selected((int) old('delegation_id', $team->delegation_id) === $delegation->id)>{{ $delegation->name }}</option>
                            @endforeach
                        </select>
                    </label>
                    <label>Categoría
                        <select name="category_id">
                            @foreach ($categories as $category)
                                <option value="{{ $category->id }}" @selected((int) old('category_id', $team->category_id) === $category->id)>{{ $category->name }}</option>
                            @endforeach
                        </select>
                    </label>
                    <label>Grupo
                        <input name="group_name" value="{{ old('group_name', $team->group_name) }}" placeholder="A">
                    </label>
                    <label>Camiseta titular
                        <input name="home_kit" value="{{ old('home_kit', $team->home_kit) }}" placeholder="Azul / Rojo">
                    </label>
                    <label>Camiseta suplente
                        <input name="away_kit" value="{{ old('away_kit', $team->away_kit) }}" placeholder="Blanca">
                    </label>
                    <label>Delegado
                        <input type="text" value="{{ $selectedDelegation?->delegate_name ?: '—' }}" readonly>
                    </label>
                    <label>Cupo jugadores
                        <input type="number" name="player_capacity" value="{{ old('player_capacity', $team->player_capacity) }}">
                    </label>
                    <label>Estado
                        <select name="status">
                            @foreach (['approved' => 'Habilitado', 'pending' => 'Pendiente', 'observed' => 'Revisión', 'blocked' => 'Bloqueado'] as $value => $label)
                                <option value="{{ $value }}" @selected(old('status', $team->status) === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </label>
                    <label>Escudo
                        <span>{{ $hasShield ? 'Cargado' : 'Sin cargar' }}</span>
                    </label>
                    <label>Torneo
                        <select name="tournament_id">
                            @foreach ($tournaments as $tournament)
                                <option value="{{ $tournament->id }}" @selected((int) old('tournament_id', $team->tournament_id) === $tournament->id)>{{ $tournament->name }}</option>
                            @endforeach
                        </select>
                    </label>
                    <label>Nombre delegación
                        <input name="delegation_name" value="{{ old('delegation_name', $team->delegation_name) }}" placeholder="San Lorenzo">
                    </label>
                    <label>Ciudad
                        <input name="city" value="{{ old('city', $team->city) }}" placeholder="Buenos Aires">
                    </label>
                    <label>País
                        <x-country-select
                            name="country_code"
                            mode="code"
                            :value="old('country_code', $team->country_code)"
                            placeholder="Sin país"
                        />
                    </label>
                </div>

                <div class="admin-team-shield">
                    <input type="hidden" name="shield_path" value="{{ old('shield_path', $team->shield_path) }}">
                    <x-ws-shield-field
                        :src="$team->exists ? $team->shieldUrl() : null"
                        heading="Escudo del club"
                        hint="Vista previa · este escudo es del club y se usa en todos los equipos de la delegación."
                    />
                </div>

                <label class="admin-team-notes">Notas
                    <textarea name="notes" rows="3">{{ old('notes', $team->notes) }}</textarea>
                </label>
            </article>

            <aside class="ws-cat-edit-side">
                <h3>Control documental</h3>
                <ul>
                    @foreach ($checks as [$label, $ok, $tone])
                        <li class="is-{{ $tone }}">
                            <span>✓</span>
                            {{ $label }}
                        </li>
                    @endforeach
                </ul>
                <button type="submit" class="stc-btn-new">PUBLICAR CAMBIOS</button>
            </aside>
        </form>
    </section>

    @if ($team->exists)
        <form method="post" action="{{ route('admin.teams.destroy', $team) }}" data-confirm="¿Eliminar {{ $team->name }} y sus partidos relacionados? Esta acción no se puede deshacer." class="admin-team-delete">
            @csrf
            @method('DELETE')
            <button class="ficha-pill reject" type="submit">Eliminar equipo</button>
        </form>
    @endif
</x-layouts.stc>
