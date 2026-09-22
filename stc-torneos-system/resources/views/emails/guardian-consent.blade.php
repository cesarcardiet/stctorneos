@component('emails.layout', get_defined_vars())
    <p>Hola {{ $player->guardian?->name ?? 'tutor/a' }},</p>
    <p>Se cargó o actualizó la ficha de <strong>{{ $player->fullName() }}</strong>@if ($player->team?->name) en el equipo <strong>{{ $player->team->name }}</strong>@endif.</p>
    @if ($player->team?->category?->name)
        <p>Categoría: <strong>{{ $player->team->category->name }}</strong></p>
    @endif
    <p>Por favor revisá los datos y confirmá la autorización parental desde el enlace seguro de abajo.</p>
@endcomponent
