@component('emails.layout', get_defined_vars())
    <p>Hola {{ $player->guardian?->name ?? 'tutor/a' }},</p>
    <p>La ficha de <strong>{{ $player->fullName() }}</strong> cambió a estado <strong>{{ $player->statusLabel() }}</strong>.</p>
    @if ($note)
        <p><strong>Detalle:</strong> {{ $note }}</p>
    @elseif ($player->observation_reason)
        <p><strong>Detalle:</strong> {{ $player->observation_reason }}</p>
    @endif
    @if ($player->team?->name)
        <p>Equipo: <strong>{{ $player->team->name }}</strong></p>
    @endif
@endcomponent
