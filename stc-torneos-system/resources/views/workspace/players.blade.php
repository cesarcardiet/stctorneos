<x-layouts.workspace
    :title="$title"
    heading="Jugadores"
    :subheading="$category->name"
    :category="$category"
    :tournament="$tournament"
    active="Jugadores"
>
    <p class="ws-back">
        <a href="{{ route('workspace.categories.teams', $category) }}">← Equipos</a>
        @if ($canManageRoster && $selectedTeam)
            <button type="button" class="stc-pill-btn ws-player-edit-inline" data-ws-open="add-player">Agregar jugador</button>
        @endif
    </p>

    @if ($rosterLockReason ?? null)
        <div class="ws-alert">{{ $rosterLockReason }}</div>
    @endif

    @if (($canManageRoster ?? false) && ! $selectedTeam)
        <p class="ws-muted ws-players-hint">Elegí un equipo en el filtro para agregar jugadores.</p>
    @endif

    <section class="ws-roster" data-live-filter>
        <header class="ws-players-head">
            <form class="ws-players-filter" method="get" action="{{ route('workspace.categories.players', $category) }}">
                <label class="ws-players-filter-label" for="ws-players-team">Equipo</label>
                <select id="ws-players-team" name="team_id" onchange="this.form.submit()">
                    <option value="">Todos los equipos</option>
                    @foreach ($teams as $team)
                        <option value="{{ $team->id }}" @selected((int) optional($selectedTeam)->id === (int) $team->id)>{{ $team->name }} ({{ $team->players_count }})</option>
                    @endforeach
                </select>
            </form>

            <p class="ws-players-count" data-live-count>{{ $players->count() }} {{ $players->count() === 1 ? 'jugador' : 'jugadores' }}</p>

            <div class="ws-players-search">
                @include('workspace.partials.live-search', [
                    'id' => 'ws-players-search',
                    'label' => '',
                    'placeholder' => 'Buscar jugador, equipo o documento…',
                    'empty' => 'No hay jugadores con esa búsqueda.',
                ])
            </div>
        </header>

        <div class="ws-roster-shell">
            <div class="ws-roster-columns" aria-hidden="true">
                <span></span>
                <span>Jugador</span>
                <span>Equipo</span>
                <span class="ws-roster-age">Edad</span>
                <span class="ws-roster-status">Estado</span>
                <span></span>
            </div>

            <div class="ws-roster-rows">
                @forelse ($players as $player)
                    @php
                        $statusTone = match ($player->status) {
                            'approved', 'enabled' => 'ok',
                            'rejected', 'blocked' => 'danger',
                            'observed' => 'warn',
                            default => 'neutral',
                        };
                    @endphp
                    <a
                        class="ws-roster-row"
                        href="{{ route('workspace.categories.players.show', [$category, $player]) }}"
                        data-live-item
                        data-search="{{ $player->fullName() }} {{ $player->last_name }} {{ $player->first_name }} {{ $player->team?->name }} {{ $player->document_number }}"
                    >
                        <div class="ws-roster-avatar" aria-hidden="true">
                            @if ($player->storedPhotoPath())
                                <img class="ws-roster-photo" src="{{ $player->photoUrl() }}" alt="">
                            @else
                                <span class="ws-roster-photo ws-roster-photo-empty">
                                    <svg viewBox="0 0 48 48" focusable="false">
                                        <circle cx="24" cy="15.5" r="6.2" fill="currentColor"/>
                                        <path fill="currentColor" d="M11.5 39.5c1.1-9.2 5.4-14 12.5-14s11.4 4.8 12.5 14c-3.6 2.3-7.9 3.5-12.5 3.5s-8.9-1.2-12.5-3.5Z"/>
                                        <path fill="#071422" d="M19.2 27.8h9.6l1.7 2.4-1.8.9-.8 6.2h-7.8l-.8-6.2-1.8-.9 1.7-2.4Z"/>
                                        <path fill="currentColor" d="M20.2 28.7h7.6l.8 1.3h-9.2l.8-1.3Z"/>
                                    </svg>
                                </span>
                            @endif
                            <img class="ws-roster-shield" src="{{ $player->team?->shieldUrl() }}" alt="">
                        </div>

                        <div class="ws-roster-name">
                            <strong>{{ strtoupper($player->last_name) }} {{ $player->first_name }}</strong>
                            @if ($player->jersey_number)
                                <small>N° {{ $player->jersey_number }}</small>
                            @endif
                        </div>

                        <span class="ws-roster-team">{{ $player->team?->name ?: '—' }}</span>

                        <span class="ws-roster-age">{{ $player->age() ? $player->age().' años' : '—' }}</span>

                        <span @class(['ws-player-status', 'ws-roster-status', 'is-'.$statusTone])>{{ $player->statusLabel() }}</span>

                        <span class="ws-roster-chevron" aria-hidden="true">›</span>
                    </a>
                @empty
                    <div class="ws-empty ws-roster-empty">No hay jugadores en este recorte.</div>
                @endforelse
            </div>
        </div>
    </section>

    @if ($canManageRoster && $selectedTeam)
        <x-ws-modal id="add-player" title="Agregar jugador · {{ $selectedTeam->name }}">
            <form method="post" action="{{ route('workspace.categories.players.store', $category) }}" class="ws-form">
                @csrf
                <input type="hidden" name="team_id" value="{{ $selectedTeam->id }}">
                <p class="ws-muted">Cargá los datos básicos. Si completás el email del tutor, al guardar se genera el enlace y se envía automáticamente a ese correo.</p>
                @if (isset($errors) && $errors->any())
                    <div class="ws-alert" role="alert">
                        {{ $errors->first() }}
                    </div>
                @endif
                <div class="ws-setup-grid">
                    <label>Apellido <input type="text" name="last_name" value="{{ old('last_name') }}" required></label>
                    <label>Nombre <input type="text" name="first_name" value="{{ old('first_name') }}" required></label>
                    <label>DNI <input type="text" name="document_number" value="{{ old('document_number') }}"></label>
                    <label>Tutor <input type="text" name="guardian_name" value="{{ old('guardian_name') }}" placeholder="Nombre del padre/madre"></label>
                    <label>Tel. tutor <input type="text" name="guardian_phone" value="{{ old('guardian_phone') }}"></label>
                    <label>Email tutor <input type="email" name="guardian_email" value="{{ old('guardian_email') }}" placeholder="Se envía el link a este correo"></label>
                </div>
                <div class="ws-form-actions">
                    <button type="submit" class="ws-btn">Guardar jugador</button>
                </div>
            </form>
        </x-ws-modal>
    @endif
</x-layouts.workspace>
