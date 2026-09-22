@php
    $player = $player ?? null;
@endphp

<article class="ws-card ws-player-public">
    <p class="stc-eyebrow">Ficha pública</p>
    <p class="ws-muted">Datos visibles del jugador en la categoría. La información privada solo la ve su club.</p>

    <div class="ws-player-public-layout">
        <div class="ws-player-photo">
            <img src="{{ $player->photoUrl() }}" alt="{{ $player->fullName() }}">
            @if ($player->hasStoredPhoto())
                <a href="{{ $player->photoUrl() }}" target="_blank" rel="noopener">Ver foto</a>
            @endif
        </div>

        <div class="ws-setup-grid">
            <div>
                <span class="ws-muted">Nombre</span>
                <strong>{{ $player->first_name }}</strong>
            </div>
            <div>
                <span class="ws-muted">Apellido</span>
                <strong>{{ $player->last_name }}</strong>
            </div>
            <div>
                <span class="ws-muted">Edad</span>
                <strong>{{ $player->age() ? $player->age().' años' : '—' }}</strong>
            </div>
            <div>
                <span class="ws-muted">Nacionalidad</span>
                <strong>{{ $player->nationality ?: '—' }}</strong>
            </div>
            <div>
                <span class="ws-muted">Camiseta</span>
                <strong>{{ $player->jersey_number ? 'N° '.$player->jersey_number : '—' }}</strong>
            </div>
            <div>
                <span class="ws-muted">Posición</span>
                <strong>{{ $player->position ?: '—' }}</strong>
            </div>
            <div>
                <span class="ws-muted">Pierna hábil</span>
                <strong>{{ $player->preferred_foot ?: '—' }}</strong>
            </div>
            <div>
                <span class="ws-muted">Habilitación</span>
                <strong>{{ $player->eligibilityLabel() }}</strong>
            </div>
        </div>
    </div>

    @if ($player->team)
        <p class="ws-muted ws-player-public-team">Equipo: {{ $player->team->name }}</p>
    @endif
</article>
