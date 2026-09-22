@php
    $player = $player ?? null;
@endphp
{{ $player->age() ? $player->age().' años' : 'sin edad' }}
@if ($player->position)
    · {{ $player->position }}
@endif
@if ($player->jersey_number)
    · N° {{ $player->jersey_number }}
@endif
· {{ $player->eligibilityLabel() }}
