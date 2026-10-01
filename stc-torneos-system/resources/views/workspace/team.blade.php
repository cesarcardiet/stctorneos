<x-layouts.workspace
    :title="$title"
    heading="{{ $team->name }}"
    :subheading="$category->name"
    :category="$category"
    :tournament="$tournament"
    :active="$active"
>
    <p class="ws-back">
        <a href="{{ route('workspace.categories.teams', $category) }}">← Equipos</a>
        <a href="{{ route('workspace.categories.teams', $category) }}">Equipos</a>
    </p>

    <section class="ws-ficha">
        @if ($canEdit)
            <article class="ws-card">
                <p class="stc-eyebrow">Equipo</p>
                <form class="ws-form" method="post" action="{{ route('workspace.categories.teams.update', [$category, $team]) }}" enctype="multipart/form-data">
                    @csrf
                    @method('PATCH')
                    <div class="ws-team-identity">
                        <x-ws-shield-field
                            :src="$team->shieldUrl()"
                            :save-url="route('workspace.categories.teams.shield', [$category, $team])"
                        />
                        <div class="ws-setup-grid">
                        <label>Nombre <input type="text" name="name" value="{{ $team->name }}" required></label>
                        <label>Club / delegación
                            <select name="delegation_id" data-ws-club-select>
                                <option value="" data-logo="{{ asset('images/stc-logo.png') }}">Nuevo club</option>
                                @foreach ($clubs as $club)
                                    <option
                                        value="{{ $club->id }}"
                                        data-logo="{{ $club->logoUrl() }}"
                                        @selected((int) old('delegation_id', $team->delegation_id) === (int) $club->id)
                                    >{{ $club->name }}</option>
                                @endforeach
                            </select>
                        </label>
                        <label data-ws-club-new @if($team->delegation_id) hidden @endif>
                            Nombre del club
                            <input type="text" name="delegation_name" value="{{ old('delegation_name', $team->delegation_name) }}" placeholder="Ej: San Lorenzo">
                        </label>
                        <label>País
                            <select name="country_code">
                                <option value="">Elegí un país</option>
                                @foreach (\App\Support\Countries::all() as $code => $label)
                                    <option value="{{ $code }}" @selected(old('country_code', $team->country_code) === $code)>{{ $label }}</option>
                                @endforeach
                            </select>
                        </label>
                        <label>Grupo
                            <select name="group_name">
                                <option value="">Sin grupo</option>
                                @foreach ($category->groupOptions($team->group_name) as $letter)
                                    <option value="{{ $letter }}" @selected(strtoupper((string) $team->group_name) === $letter)>{{ $category->groupDisplayName($letter) }}</option>
                                @endforeach
                            </select>
                        </label>
                        <label>Estado
                            <select name="status">
                                @foreach (\App\Models\Team::statusLabels() as $value => $label)
                                    <option value="{{ $value }}" @selected($team->status === $value)>{{ $label }}</option>
                                @endforeach
                            </select>
                        </label>
                        </div>
                    </div>
                    <div class="ws-form-actions">
                        <button type="submit" class="ws-btn">Guardar</button>
                    </div>
                </form>
            </article>
        @endif

        <article class="ws-card">
            <p class="stc-eyebrow">Cuerpo técnico</p>
            @if ($canEdit)
                <form class="ws-inline-add" method="post" action="{{ route('workspace.categories.teams.staff', [$category, $team]) }}">
                    @csrf
                    <input type="text" name="last_name" placeholder="Apellido" required>
                    <input type="text" name="first_name" placeholder="Nombre" required>
                    <select name="role" required>
                        @foreach ($staffRoles as $value => $label)
                            <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </select>
                    <button type="submit" class="ws-btn">Añadir</button>
                </form>
            @endif
            <ul class="ws-people">
                @forelse ($team->staffMembers as $staff)
                    <li><strong>{{ $staff->fullName() }}</strong> <span>{{ $staff->roleLabel() }}</span></li>
                @empty
                    <li class="ws-muted">Todavía no hay cuerpo técnico.</li>
                @endforelse
            </ul>
        </article>

        <article class="ws-card">
            <p class="stc-eyebrow">Plantel ({{ $team->players->count() }})</p>
            <p class="ws-muted">Si el jugador tiene un archivo cargado, figura como aprobado.</p>
            @if ($rosterLockReason ?? null)
                <div class="ws-alert">{{ $rosterLockReason }}</div>
            @endif
            @if ($canManageRoster ?? false)
                <form class="ws-form" method="post" action="{{ route('workspace.categories.players.store', $category) }}">
                    @csrf
                    <input type="hidden" name="team_id" value="{{ $team->id }}">
                    <div class="ws-setup-grid">
                        <label>Apellido <input type="text" name="last_name" value="{{ old('last_name') }}" required></label>
                        <label>Nombre <input type="text" name="first_name" value="{{ old('first_name') }}" required></label>
                        <label>DNI <input type="text" name="document_number" value="{{ old('document_number') }}"></label>
                        <label>Tutor <input type="text" name="guardian_name" value="{{ old('guardian_name') }}" placeholder="Nombre del padre o la madre"></label>
                        <label>Tel. tutor <input type="text" name="guardian_phone" value="{{ old('guardian_phone') }}"></label>
                        <label>Email tutor <input type="email" name="guardian_email" value="{{ old('guardian_email') }}" placeholder="Si lo cargás, se envía el enlace"></label>
                    </div>
                    <div class="ws-form-actions">
                        <button type="submit" class="ws-btn">Agregar jugador</button>
                    </div>
                </form>
            @endif
            <div class="ws-list">
                @forelse ($team->players as $player)
                    <a class="ws-list-row" href="{{ route('workspace.categories.players.show', [$category, $player]) }}">
                        <div>
                            <strong>{{ strtoupper($player->last_name) }} {{ $player->first_name }}</strong>
                            <span>{{ $player->rosterPhaseLabel() }}@if ($player->guardianDisplayName()) · Tutor: {{ $player->guardianDisplayName() }}@endif</span>
                        </div>
                        <span class="ws-ficha-btn">Ficha</span>
                    </a>
                @empty
                    <p class="ws-muted">Todavía no hay jugadores en este equipo.</p>
                @endforelse
            </div>
        </article>
    </section>
</x-layouts.workspace>
