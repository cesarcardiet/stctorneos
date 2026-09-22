<x-layouts.workspace
    :title="$title"
    heading="Inscripciones"
    :subheading="$category->name"
    :category="$category"
    :tournament="$tournament"
    active="Inscripciones"
>
    @if (! empty($canEdit))
        <p class="ws-back"><a href="{{ route('workspace.categories.settings', $category) }}">← Configuración</a></p>
    @elseif (! empty($clubScoped))
        <p class="ws-back"><a href="{{ route('workspace.categories.home', $category) }}">← Inicio categoría</a></p>
    @endif

    <section class="ws-inscription-head">
        <div class="ws-inscription-status">
            <span class="ws-reg-badge {{ ($workspace['registrations_open'] ?? false) ? 'is-open' : 'is-closed' }}">
                {{ ($workspace['registrations_open'] ?? false) ? 'Inscripciones abiertas' : 'Inscripciones cerradas' }}
            </span>
            @if (! empty($canEdit))
                <button type="button" class="ws-btn ghost" data-ws-open="registrations">Abrir / cerrar</button>
            @endif
        </div>
        @if (filled($workspace['registration_info'] ?? null))
            <p class="ws-muted ws-inscription-info">{{ $workspace['registration_info'] }}</p>
        @elseif (! ($workspace['registrations_open'] ?? false))
            <p class="ws-muted ws-inscription-info">Con las inscripciones cerradas, solo administración puede agregar equipos o aprobar fichas.</p>
        @endif
    </section>

    <article class="ws-card ws-inscription-band is-waiting">
        <header class="ws-inscription-band-head">
            <div>
                <p class="stc-eyebrow">Esperando aprobación</p>
                <h2>{{ $waitingCount }} {{ $waitingCount === 1 ? 'ficha' : 'fichas' }}</h2>
            </div>
            @if (! empty($clubScoped))
                <p class="ws-muted">Solo tu club en esta categoría.</p>
            @endif
        </header>

        <div class="ws-inscription-grid">
            @forelse ($pending as $player)
                @include('workspace.partials.inscription-card', [
                    'player' => $player,
                    'category' => $category,
                    'canReview' => $canReview ?? false,
                    'tone' => 'waiting',
                ])
            @empty
                <div class="ws-empty ws-inscription-grid-empty">No hay fichas esperando aprobación.</div>
            @endforelse
        </div>
    </article>

    <article class="ws-card ws-inscription-band is-rejected">
        <header class="ws-inscription-band-head">
            <div>
                <p class="stc-eyebrow">Rechazado</p>
                <h2>{{ $rejectedCount }} {{ $rejectedCount === 1 ? 'ficha' : 'fichas' }}</h2>
            </div>
        </header>

        <div class="ws-inscription-grid">
            @forelse ($rejected as $player)
                @include('workspace.partials.inscription-card', [
                    'player' => $player,
                    'category' => $category,
                    'canReview' => $canReview ?? false,
                    'tone' => 'rejected',
                ])
            @empty
                <div class="ws-empty ws-inscription-grid-empty">No hay inscripciones rechazadas.</div>
            @endforelse
        </div>
    </article>

    @if (! empty($canEdit))
        @include('workspace.partials.config-modals')
    @endif
</x-layouts.workspace>
