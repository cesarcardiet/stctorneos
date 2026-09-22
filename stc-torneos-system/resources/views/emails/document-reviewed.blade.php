@component('emails.layout', get_defined_vars())
    <p>Hola {{ $player?->guardian?->name ?? 'tutor/a' }},</p>
    <p>La documentación <strong>{{ $document->type }}</strong> de <strong>{{ $player?->fullName() }}</strong> fue marcada como <strong>{{ $document->statusLabel() }}</strong>.</p>
    @if ($document->notes)
        <p><strong>Observación:</strong> {{ $document->notes }}</p>
    @endif
    <p>Contactá al delegado del club o ingresá al sistema para continuar con la gestión.</p>
@endcomponent
