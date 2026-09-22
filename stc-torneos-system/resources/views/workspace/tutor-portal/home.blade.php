<x-layouts.workspace
    :title="$title"
    :heading="$heading"
    :subheading="$subheading"
    :active="$active"
>
    @if (session('status'))
        <div class="login-alert">{{ session('status') }}</div>
    @endif

    <section class="ws-ficha">
        <article class="ws-card">
            <p class="stc-eyebrow">Tu acceso</p>
            <p class="ws-muted">Correo: <strong>{{ $user->email }}</strong></p>
            <p class="ws-muted">Clave inicial de tutores nuevos: <strong>{{ $defaultPasswordHint }}</strong> (cambiala en <a href="{{ route('workspace.account') }}">Mi cuenta</a>).</p>
        </article>

        <article class="ws-card">
            <p class="stc-eyebrow">Torneos</p>
            <p class="ws-muted">Consultá clasificación, fixture, equipos, jugadores y rankings de <strong>todos los torneos</strong> (solo lectura).</p>
            <a class="ws-btn ghost" href="{{ route('workspace.home') }}" style="margin-top: .75rem;">Ver torneos y categorías</a>
        </article>

        @forelse ($players as $player)
            <article class="ws-card">
                <p class="stc-eyebrow">{{ $player->team?->tournament?->name ?? 'Torneo' }}</p>
                <h2 style="margin: .35rem 0 .5rem;">{{ $player->fullName() }}</h2>
                <p class="ws-muted">
                    {{ $player->team?->name ?? 'Sin equipo' }}
                    @if ($player->team?->category)
                        · {{ $player->team->category->name }}
                    @endif
                </p>
                <p class="ws-muted">
                    Estado ficha: <strong>{{ $player->reviewStatusLabel() }}</strong>
                    · Tutor: <strong>{{ $player->guardianConfirmationSigned() ? 'Firmado' : 'Pendiente' }}</strong>
                </p>

                @php
                    $fichaLocked = $player->tutorFichaLocked();
                @endphp

                <div class="ws-tutor-actions" style="margin-top: 1rem; display: flex; gap: .75rem; flex-wrap: wrap;">
                    @if ($fichaLocked)
                        <a class="ws-btn ghost" href="{{ route('workspace.tutor.ficha', $player) }}">Ver ficha enviada</a>
                        <span class="ws-tutor-pill is-done">En revisión</span>
                    @else
                        <a class="ws-btn" href="{{ route('workspace.tutor.ficha', $player) }}">Completar ficha</a>
                    @endif
                </div>
                @if ($fichaLocked)
                    <p class="ws-muted" style="margin-top: .75rem;">Si necesitás modificar algo, pedile al administrador o delegado del torneo.</p>
                @endif
            </article>
        @empty
            <article class="ws-card">
                <p class="ws-muted">Todavía no hay jugadores vinculados a tu correo. Cuando un delegado te invite como tutor, van a aparecer acá.</p>
            </article>
        @endforelse
    </section>
</x-layouts.workspace>
