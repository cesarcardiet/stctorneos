<x-layouts.workspace
    :title="$title"
    heading="Personas con acceso"
    :subheading="$tournament->name"
    :category="$category"
    :tournament="$tournament"
    active="Configuración"
>
    <p class="ws-back"><a href="{{ route('workspace.categories.settings', $category) }}">← Configuración</a></p>

    @if ($canManageUsers)
        <form class="ws-inline-add" method="post" action="{{ route('workspace.categories.people.store', $category) }}">
            @csrf
            <input type="text" name="name" placeholder="Nombre" required>
            <input type="email" name="email" placeholder="Email" required>
            <select name="role_id" required>
                @foreach ($roles as $role)
                    <option value="{{ $role->id }}">{{ $role->name }}</option>
                @endforeach
            </select>
            <select name="delegation_id">
                <option value="">Club / delegación</option>
                @foreach ($clubs as $club)
                    <option value="{{ $club->id }}">{{ $club->name }}</option>
                @endforeach
            </select>
            <button type="submit" class="ws-btn">Agregar</button>
        </form>
        <p class="ws-muted">Entran con la misma clave de demo.</p>
    @endif

    <div data-live-filter>
        @include('workspace.partials.live-search', [
            'placeholder' => 'Buscar nombre, email o rol…',
            'empty' => 'No hay personas con esa búsqueda.',
        ])
        <div class="ws-list">
            @forelse ($people as $person)
                <div
                    class="ws-list-row"
                    data-live-item
                    data-search="{{ $person->name }} {{ $person->email }} {{ $person->roleLabel() }} {{ $person->delegation?->name }}"
                >
                <div>
                    <strong>{{ $person->name }}</strong>
                    <span>{{ $person->roleLabel() }} · {{ $person->email }}{{ $person->delegation ? ' · '.$person->delegation->name : '' }}</span>
                </div>
            </div>
        @empty
            <div class="ws-empty">Nadie con alcance a este torneo.</div>
        @endforelse
        </div>
    </div>
</x-layouts.workspace>
