@php
    $delegateUsers = $delegateUsers ?? collect();
    $assignedDelegates = collect($assignedDelegates ?? $club->credentialDelegates());
    $assignedDelegate = $assignedDelegate ?? $club->principalDelegate();
    $selectedDelegateIds = collect(old('delegate_user_ids', $assignedDelegates->pluck('id')->all()))
        ->map(fn ($id) => (int) $id)
        ->filter()
        ->unique()
        ->values();
    $delegatePeopleById = $delegateUsers->concat($assignedDelegates)->unique('id')->keyBy('id');
    $selectedDelegates = $selectedDelegateIds
        ->map(fn ($id) => $delegatePeopleById->get($id))
        ->filter();
    $delegateTagOptions = $delegateUsers->map(fn ($person) => [
        'id' => $person->id,
        'name' => $person->name,
        'email' => $person->email,
    ])->values();
    $canManageClubs = $canManageClubs ?? $canEdit;
    $canAssignDelegates = $canAssignDelegates ?? $canEdit;
    $clubListUrl = $category
        ? route('workspace.categories.clubs', $category)
        : route('workspace.tournaments.clubs', $tournament);
    $clubUpdateUrl = $category
        ? route('workspace.categories.clubs.update', [$category, $club])
        : route('workspace.tournaments.clubs.update', [$tournament, $club]);
    $clubShieldUrl = $category
        ? route('workspace.categories.clubs.shield', [$category, $club])
        : route('workspace.tournaments.clubs.shield', [$tournament, $club]);
    $clubDestroyUrl = $category
        ? route('workspace.categories.clubs.destroy', [$category, $club])
        : route('workspace.tournaments.clubs.destroy', [$tournament, $club]);
    $delegateStoreUrl = $category
        ? route('workspace.categories.clubs.delegate', [$category, $club])
        : route('workspace.tournaments.clubs.delegate', [$tournament, $club]);
@endphp

<x-layouts.workspace
    :title="$title"
    heading="{{ $club->name }}"
    :subheading="$tournament->name"
    :category="$category"
    :tournament="$tournament"
    active="Delegaciones"
>
    <p class="ws-back">
        @if ($category)
            <a href="{{ route('workspace.categories.settings', $category) }}">← Configuración</a>
        @endif
        <a href="{{ $clubListUrl }}">Delegaciones</a>
    </p>

    @if (session('status'))
        <div class="ws-alert" @if (session('delegate_welcome_open_whatsapp') && session('delegate_welcome_whatsapp')) data-ws-open-whatsapp="{{ session('delegate_welcome_whatsapp') }}" @endif>
            {{ session('status') }}
            @if (session('delegate_welcome_whatsapp'))
                <p style="margin-top:10px;">
                    <a class="ws-btn" href="{{ session('delegate_welcome_whatsapp') }}" target="_blank" rel="noopener">Enviar acceso por WhatsApp</a>
                </p>
                <p class="ws-muted" style="margin-top:8px;">
                    Se abre WhatsApp con el mensaje listo (correo, clave generada por el sistema y link de ingreso). Solo tenés que tocar enviar.
                </p>
            @endif
        </div>
    @endif

    <section class="ws-ficha">
        @if ($canEdit)
            <article class="ws-card">
                <p class="stc-eyebrow">Delegación / club</p>
                <form class="ws-form" method="post" action="{{ $clubUpdateUrl }}" enctype="multipart/form-data">
                    @csrf
                    @method('PATCH')
                    <div class="ws-team-identity">
                        <x-ws-shield-field
                            :src="$club->logoUrl()"
                            :save-url="$clubShieldUrl"
                        />
                        <div class="ws-setup-grid">
                            <label>Nombre <input type="text" name="name" value="{{ old('name', $club->name) }}" required></label>
                            <label>País
                                <x-country-select
                                    name="country_code"
                                    mode="code"
                                    :value="old('country_code', $club->countryCode())"
                                    placeholder="Elegí un país"
                                />
                            </label>
                            <label>Ciudad <input type="text" name="city" value="{{ old('city', $club->city) }}" placeholder="Ej: Buenos Aires"></label>
                            <label class="ws-delegate-field">
                                <span class="ws-field-label">Responsable <em class="ws-role-chip">Delegado</em></span>
                                <span class="ws-muted">Usá las credenciales del torneo. Podés asignar más de uno: cada persona entra con su correo y su clave.</span>
                                @if ($canAssignDelegates)
                                    <input type="hidden" name="sync_delegates" value="1">
                                    <div class="ws-delegate-tags" data-ws-delegate-tags>
                                        <div class="ws-delegate-chips" data-ws-delegate-chips>
                                            @forelse ($selectedDelegates as $person)
                                                <span class="ws-delegate-chip" data-id="{{ $person->id }}">
                                                    <em>{{ $person->name }}</em>
                                                    <button type="button" class="ws-delegate-chip-x" data-ws-delegate-remove aria-label="Quitar {{ $person->name }}">×</button>
                                                    <input type="hidden" name="delegate_user_ids[]" value="{{ $person->id }}">
                                                </span>
                                            @empty
                                                <span class="ws-delegate-empty" data-ws-delegate-empty>Todavía no hay delegados asignados.</span>
                                            @endforelse
                                        </div>
                                        <div class="ws-delegate-pick">
                                            <input type="search" data-ws-delegate-filter placeholder="Buscar y agregar…" autocomplete="off">
                                            <button type="button" class="ws-btn ghost" data-ws-open="new-delegate">Nuevo</button>
                                        </div>
                                        <div class="ws-delegate-suggest" data-ws-delegate-suggest hidden></div>
                                        <script type="application/json" data-ws-delegate-options>@json($delegateTagOptions)</script>
                                    </div>
                                @elseif ($assignedDelegates->isNotEmpty())
                                    <ul class="ws-people">
                                        @foreach ($assignedDelegates as $person)
                                            <li><strong>{{ $person->name }}</strong> <span>{{ $person->email }}</span></li>
                                        @endforeach
                                    </ul>
                                @else
                                    <p class="ws-muted">Sin delegado asignado.</p>
                                @endif
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
            <p class="stc-eyebrow">Equipos ({{ $club->teams_count }})</p>
            <p class="ws-muted">Estos equipos quedan atados a esta delegación en todas las categorías.</p>
            @if ($canEdit)
                @php $categories = $categories ?? collect(); @endphp
                @if ($categories->isNotEmpty())
                    <form class="ws-inline-add ws-club-team-add" method="post" action="{{ route('workspace.tournaments.teams.store', $tournament) }}">
                        @csrf
                        <input type="hidden" name="delegation_id" value="{{ $club->id }}">
                        <select name="category_id" required>
                            <option value="">Categoría</option>
                            @foreach ($categories as $item)
                                <option value="{{ $item->id }}" @selected((int) old('category_id', $category?->id) === (int) $item->id)>{{ $item->name }}</option>
                            @endforeach
                        </select>
                        <input type="text" name="name" value="{{ old('name', $club->name) }}" placeholder="Nombre del equipo">
                        <button type="submit" class="ws-btn">Añadir equipo</button>
                    </form>
                @else
                    <p class="ws-muted">Primero creá una categoría en este torneo.</p>
                @endif
            @endif
            <div class="ws-list">
                @forelse ($club->teams as $team)
                    <div class="ws-list-row">
                        <img class="ws-team-shield" src="{{ $team->shieldUrl() }}" alt="">
                        @if ($team->category)
                            <a href="{{ route('workspace.categories.teams.show', [$team->category, $team]) }}">
                                <strong>{{ strtoupper($team->name) }}</strong>
                                <span>
                                    {{ $team->category->name }}
                                    @if ($team->group_name)
                                        · {{ $team->category->groupDisplayName($team->group_name) }}
                                    @endif
                                    · {{ $team->category->publicRegistrationLabel() }}
                                </span>
                            </a>
                        @else
                            <div>
                                <strong>{{ strtoupper($team->name) }}</strong>
                                <span>{{ $team->group_name ? 'Grupo '.$team->group_name : 'Sin categoría' }}</span>
                            </div>
                        @endif
                        @if ($canEdit && $team->category)
                            <span class="ws-row-actions">
                                <a href="{{ route('workspace.categories.teams.show', [$team->category, $team]) }}">Editar</a>
                                <form method="post" action="{{ route('workspace.categories.teams.destroy', [$team->category, $team]) }}" data-confirm="¿Eliminar {{ $team->name }}? Se borran jugadores y partidos de este equipo.">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="is-danger">Eliminar</button>
                                </form>
                            </span>
                        @endif
                    </div>
                @empty
                    <div class="ws-empty">Todavía no hay equipos en este club.</div>
                @endforelse
            </div>
        </article>

        <article class="ws-card">
            <p class="stc-eyebrow">Delegados ({{ $assignedDelegates->count() ?: $club->delegates_count }})</p>
            <p class="ws-muted">Cada uno entra con su correo y su clave. Si hay varios, cada credencial es independiente y solo ve este club.</p>
            <ul class="ws-people">
                @forelse ($assignedDelegates as $person)
                    <li class="ws-person-row">
                        <span class="ws-person-avatar" aria-hidden="true">{{ $person->initials() }}</span>
                        <div>
                            <strong>{{ $person->name }}</strong>
                            <span>{{ $person->email }}{{ $person->phone ? ' · '.$person->phone : '' }}</span>
                        </div>
                        <x-whatsapp-link
                            :url="app(\App\Services\DelegateWelcomeService::class)->whatsappUrl($person, $tournament, $club)"
                        />
                    </li>
                @empty
                    <li class="ws-muted">Todavía no hay un usuario delegado asignado. Crealo con Nuevo.</li>
                @endforelse
            </ul>
            @if ($canAssignDelegates)
                <p class="ws-back"><button type="button" class="ws-link-btn" data-ws-open="new-delegate">Agregar delegado</button></p>
            @endif
        </article>

        @if ($canManageClubs)
            <form method="post" action="{{ $clubDestroyUrl }}" data-confirm="¿Eliminar {{ $club->name }}? Los equipos quedan, pero sin esta delegación.">
                @csrf
                @method('DELETE')
                <button type="submit" class="ws-btn danger">Eliminar club</button>
            </form>
        @endif
    </section>

    @if ($canAssignDelegates)
        <x-ws-modal id="new-delegate" title="Nuevo delegado">
            <form class="ws-form" method="post" action="{{ $delegateStoreUrl }}" data-ws-new-delegate-form>
                @csrf
                <p class="ws-muted">Queda asignado a {{ $club->name }} sin sacar a los que ya están. El sistema crea una clave y te abre WhatsApp con el acceso (correo, clave y link de ingreso) para enviárselo.</p>
                <label>Nombre y apellido <input type="text" name="name" value="" required placeholder="Ej: Ana Delegada"></label>
                <label>Correo <input type="email" name="email" value="" required placeholder="ana@club.com"></label>
                <label>Teléfono (WhatsApp) <input type="text" name="phone" value="" required placeholder="+54 9 11 ..."></label>
                <div class="ws-form-actions">
                    <button type="button" class="ws-btn ghost" data-ws-close>Cancelar</button>
                    <button type="submit" class="ws-btn">Crear y asignar</button>
                </div>
            </form>
        </x-ws-modal>
    @endif
</x-layouts.workspace>
