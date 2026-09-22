@php
    $registrationsOpen = (bool) ($workspace['registrations_open'] ?? true);
    $drawRule = $workspace['draw_rule'] ?? 'Penales';
    $fairPlayOn = array_key_exists('fair_play_on', $workspace) ? (bool) $workspace['fair_play_on'] : true;
    $hasEliminatory = collect($workspace['phases'] ?? [])->contains(fn ($phase) => ($phase['mode'] ?? '') === 'Eliminatoria');
    $checks = [
        ['Criterios de tabla configurados', ! empty($workspace['visible_columns']), 'ok'],
        ['Fases de eliminación activas', $hasEliminatory, $hasEliminatory ? 'ok' : 'warn'],
        ['Reglas Fair Play asignadas', $fairPlayOn, 'ok'],
        ['Calendario sin conflictos', ($fields ?? collect())->isNotEmpty(), ($fields ?? collect())->isNotEmpty() ? 'ok' : 'warn'],
        ['Visible para delegados', $category->status === 'active', $category->status === 'active' ? 'ok' : 'warn'],
    ];
@endphp

<x-layouts.workspace
    :title="$title"
    heading="Crear / Editar Categoría"
    :subheading="'Reglas deportivas por edad, modalidad, cupos, fases y criterios de clasificación.'"
    :category="$category"
    :tournament="$tournament"
    :active="$active"
>
    <p class="ws-ficha-back">
        <a href="{{ route('workspace.categories.home', $category) }}">← {{ $category->name }}</a>
    </p>

    <section class="ws-cat-edit">
        <header class="ws-cat-edit-bar">
            <div>
                <strong>Crear / Editar Categoría</strong>
                <span>{{ $category->name }} · {{ $tournament->name }}</span>
            </div>
            @if ($canEdit)
                <button type="submit" form="cat-profile" class="ws-btn">GUARDAR</button>
            @endif
        </header>

        <form id="cat-profile" class="ws-cat-edit-grid" method="post" action="{{ route('workspace.categories.settings.update', $category) }}">
            @csrf
            <input type="hidden" name="section" value="profile">

            <article class="ws-cat-edit-card">
                <p class="stc-eyebrow">Datos básicos</p>
                <div class="ws-cat-fields">
                    <label>Categoría
                        <input type="text" name="name" value="{{ $category->name }}" required>
                    </label>
                    <label>Modalidad
                        <select name="modality" required>
                            @foreach ($modalities ?? [] as $modality)
                                <option value="{{ $modality }}" @selected($category->modality === $modality)>{{ $modality }}</option>
                            @endforeach
                        </select>
                    </label>
                    <label>Cupo equipos
                        <input type="number" name="team_limit" min="0" max="128" value="{{ $category->team_limit }}">
                    </label>
                    <label>Cupo jugadores
                        <input type="number" name="max_players" min="1" max="99" value="{{ $category->max_players }}">
                    </label>
                    <label>Formato
                        <select name="competition_format">
                            @foreach ($competitionFormats ?? [] as $format)
                                <option value="{{ $format }}" @selected($category->competition_format === $format)>{{ $format }}</option>
                            @endforeach
                        </select>
                    </label>
                    <label>Duración
                        <span class="ws-cat-duration">
                            <input type="number" name="periods" min="1" max="4" value="{{ $category->periods ?: 2 }}">
                            <em>x</em>
                            <input type="number" name="period_duration" min="1" max="90" value="{{ $category->period_duration ?: 20 }}">
                            <em>min</em>
                        </span>
                    </label>
                    <label>Empate
                        <select name="draw_rule">
                            @foreach (['Penales', 'Tiempo extra', 'Empate vale'] as $rule)
                                <option value="{{ $rule }}" @selected($drawRule === $rule)>{{ $rule }}</option>
                            @endforeach
                        </select>
                    </label>
                    <label>Fair Play
                        <select name="fair_play_on">
                            <option value="1" @selected($fairPlayOn)>Activo</option>
                            <option value="0" @selected(! $fairPlayOn)>Inactivo</option>
                        </select>
                    </label>
                    <label>Cambios
                        <input type="number" name="substitutes" min="0" max="99" value="{{ $category->substitutes }}">
                    </label>
                    <label>Estado
                        <select name="status">
                            @foreach ($statusLabels ?? [] as $value => $label)
                                <option value="{{ $value }}" @selected($category->status === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </label>
                </div>
            </article>

            <aside class="ws-cat-edit-side">
                <h3>Control documental</h3>
                <ul>
                    @foreach ($checks as [$label, $ok, $tone])
                        <li class="is-{{ $tone }}">
                            <span>{{ $ok ? '✓' : '!' }}</span>
                            {{ $label }}
                        </li>
                    @endforeach
                </ul>
                @if ($canEdit)
                    <button type="submit" name="publish" value="1" class="ws-btn">PUBLICAR CAMBIOS</button>
                @endif
            </aside>
        </form>

        @if ($canEdit)
            <article class="ws-card ws-inscriptions-control">
                <p class="stc-eyebrow">Inscripciones de planteles</p>
                <p class="ws-muted">Controlá si los delegados pueden cargar o editar jugadores en esta categoría.</p>
                <p>
                    Estado actual:
                    <strong>{{ $registrationsOpen ? 'Abiertas' : 'Cerradas' }}</strong>
                </p>
                <form class="ws-form" method="post" action="{{ route('workspace.categories.settings.update', $category) }}">
                    @csrf
                    <input type="hidden" name="section" value="registrations">
                    <label class="ws-check">
                        <input type="hidden" name="registrations_open" value="0">
                        <input type="checkbox" name="registrations_open" value="1" @checked($registrationsOpen)>
                        Inscripciones abiertas para delegados
                    </label>
                    <label>Mensaje para delegados (opcional)
                        <textarea name="registration_info" rows="3" placeholder="Ej: Cierre de inscripciones el viernes 15 a las 18 hs.">{{ $workspace['registration_info'] ?? '' }}</textarea>
                    </label>
                    <div class="ws-form-actions">
                        <button type="submit" class="ws-btn">{{ $registrationsOpen ? 'Guardar / cerrar ahora' : 'Guardar / abrir ahora' }}</button>
                    </div>
                </form>
            </article>
        @endif

        <div class="ws-cat-edit-more">
            <article class="ws-card">
                <p class="stc-eyebrow">Campeonato</p>
                <div class="ws-edit-rows">
                    <div><span>Delegaciones ({{ $clubCount }})</span> <a href="{{ route('workspace.tournaments.clubs', $tournament) }}">Editar</a></div>
                    <div><span>Equipos ({{ $category->teams->count() }})</span> <a href="{{ route('workspace.categories.teams', $category) }}">Editar</a></div>
                    <div><span>Jugadores ({{ $playerCount }})</span> <a href="{{ route('workspace.categories.players', $category) }}">Editar</a></div>
                    <div><span>Documentación ({{ $pendingDocs }} pendientes)</span> <a href="{{ route('workspace.categories.documents', $category) }}">Revisar</a></div>
                    <div><span>Inscripciones ({{ $pendingPlayers }} pendientes)</span> <a href="{{ route('workspace.categories.inscriptions', $category) }}">Revisar</a></div>
                    <div><span>Grupos ({{ $category->groups_count }})</span> <button type="button" data-ws-open="groups">Editar</button></div>
                    <div><span>Fases</span> <button type="button" data-ws-open="phases">Editar</button></div>
                    <div><span>Fechas</span> <button type="button" data-ws-open="rounds">Editar</button></div>
                    <div><span>Arbitraje</span> <button type="button" data-ws-open="official">Editar</button></div>
                    <div><span>Sitios</span> <button type="button" data-ws-open="sites">Editar</button></div>
                    <div><span>Criterios de clasificación</span> <button type="button" data-ws-open="criteria">Editar</button></div>
                    <div><span>Editar inscripciones</span> <button type="button" data-ws-open="registrations">Editar</button></div>
                    <div><span>Rankings y encuestas</span> <a href="{{ route('workspace.categories.rankings', $category) }}">Editar</a></div>
                    <div><span>Resultado del campeonato</span> <button type="button" data-ws-open="result">Editar</button></div>
                </div>
            </article>

            <article class="ws-card">
                <p class="stc-eyebrow">Divulgación</p>
                <div class="ws-edit-rows">
                    <div><span>Personas con acceso</span> <a href="{{ route('workspace.categories.people', $category) }}">Editar</a></div>
                    <div><span>Historial de cambios</span> <a href="{{ route('workspace.categories.history', $category) }}">Ver</a></div>
                </div>
            </article>

            <article class="ws-card">
                <p class="stc-eyebrow">Ajustes deportivos</p>
                <div class="ws-edit-rows">
                    <div><span>Puntos victoria / empate / derrota</span> <button type="button" data-ws-open="sport">Editar</button></div>
                    <div><span>{{ $category->points_win }} / {{ $category->points_draw }} / {{ $category->points_loss }}</span></div>
                </div>
            </article>
        </div>
    </section>

    @include('workspace.partials.report-modals', [
        'category' => $category,
        'workspace' => $workspace,
        'columnLabels' => $columnLabels,
    ])

    @if ($canEdit)
        @include('workspace.partials.config-modals')

        <x-ws-modal id="sites" title="Sitios">
            <ul class="ws-people">
                @forelse ($fields as $field)
                    <li><strong>{{ $field->name }}</strong> <span>{{ $field->venue?->name }}</span></li>
                @empty
                    <li class="ws-muted">No hay canchas cargadas en este torneo.</li>
                @endforelse
            </ul>
            <x-ws-form :action="route('workspace.categories.fields.store', $category)" submit="Agregar cancha">
                <label>Nombre de la cancha <input type="text" name="name" required></label>
            </x-ws-form>
        </x-ws-modal>
    @endif
</x-layouts.workspace>
