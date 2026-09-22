<x-layouts.stc
    title="Usuarios y Roles | STC Torneos"
    active="Usuarios"
    heading="Usuarios y Roles"
    subheading="Permisos por administrador, delegado, asistente y árbitro."
>
    @if (session('status'))
        <div class="system-alert">{{ session('status') }}</div>
    @endif

    @if (auth()->user()?->hasPermission('roles.manage'))
        <p class="player-filter-bar">
            <a class="stc-button ghost" href="{{ route('admin.roles.index') }}">Gestionar roles y permisos</a>
        </p>
    @endif

    <section class="admin-figma-kpis">
        @foreach ($stats as [$value, $label, $tone])
            <article class="admin-figma-kpi tone-{{ $tone }}">
                <strong>{{ $value }}</strong>
                <span>{{ $label }}</span>
            </article>
        @endforeach
    </section>

    <form class="category-filter-bar player-filter-bar users-filter-bar" method="get" action="{{ route('admin.users.index') }}">
        <input name="search" value="{{ $filters['search'] }}" placeholder="Buscar nombre, mail o rol">
        <select name="role_id">
            <option value="">Todos los roles</option>
            @foreach ($roles as $role)
                <option value="{{ $role->id }}" @selected((string) $filters['role_id'] === (string) $role->id)>{{ $role->name }}</option>
            @endforeach
        </select>
        <select name="status">
            <option value="all" @selected($filters['status'] === 'all')>Estado: todos</option>
            @foreach (\App\Models\User::statusLabels() as $value => $label)
                <option value="{{ $value }}" @selected($filters['status'] === $value)>{{ $label }}</option>
            @endforeach
        </select>
        <select name="tournament_id">
            <option value="">Todos los torneos</option>
            @foreach ($tournaments as $tournament)
                <option value="{{ $tournament->id }}" @selected((string) $filters['tournament_id'] === (string) $tournament->id)>{{ $tournament->name }}</option>
            @endforeach
        </select>
        <button type="submit">Filtrar</button>
        <a href="{{ route('admin.users.index') }}">Limpiar</a>
    </form>

    <section class="sheets-list-card admin-figma-panel">
        <h3>Matriz de usuarios</h3>
        <div class="delegation-figma-table sheets-figma-table admin-figma-table users-figma-table">
            <div class="table-head">
                <span>Usuario</span>
                <span>Rol</span>
                <span>Torneo</span>
                <span>Estado</span>
                <span>Acción</span>
            </div>
            @forelse ($users as $listed)
                @php
                    $statusClass = match ($listed->status) {
                        'active' => 'is-approved',
                        'pending' => 'is-pending',
                        default => 'is-blocked',
                    };
                @endphp
                <div class="table-row">
                    <span>
                        <x-entity-cell
                            :href="route('admin.users.show', $listed)"
                            :src="$listed->photoUrl()"
                            :alt="$listed->name"
                            shape="round"
                        >
                            {{ $listed->name }}
                            <x-slot:subtitle>{{ $listed->email }}</x-slot:subtitle>
                        </x-entity-cell>
                    </span>
                    <span>{{ $listed->roleLabel() }}</span>
                    <span>{{ $listed->tournamentLabel() }}</span>
                    <span class="admin-status {{ $statusClass }}">{{ $listed->statusLabel() }}</span>
                    <span class="category-actions">
                        @if ($listed->status === 'pending' && $listed->id !== auth()->id())
                            <form method="post" action="{{ route('admin.users.status', $listed) }}" data-confirm="¿Aprobar el acceso de {{ $listed->name }}?">
                                @csrf
                                @method('PATCH')
                                <input type="hidden" name="status" value="active">
                                <button type="submit">Aprobar</button>
                            </form>
                        @endif
                        <a href="{{ route('admin.users.show', $listed) }}">Ver</a>
                        <a href="{{ route('admin.users.edit', $listed) }}">Editar</a>
                    </span>
                </div>
            @empty
                <div class="category-empty-state"><strong>No hay usuarios con esa búsqueda.</strong></div>
            @endforelse
        </div>
    </section>
</x-layouts.stc>
