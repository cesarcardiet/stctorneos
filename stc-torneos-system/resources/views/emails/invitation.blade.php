@component('emails.layout', get_defined_vars())
    <p>Hola {{ $invitation->name }},</p>
    <p>Te invitaron a acceder a <strong>STC Torneos</strong>@if ($invitation->role?->name) como <strong>{{ $invitation->role->name }}</strong>@endif.</p>
    <p>Usá el botón de abajo para activar tu cuenta y definir tu contraseña. El enlace vence el {{ $invitation->expires_at?->format('d/m/Y H:i') ?? '—' }}.</p>
@endcomponent
