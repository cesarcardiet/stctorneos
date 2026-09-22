@component('emails.layout', get_defined_vars())
    <p>Hola {{ $user->name }},</p>
    <p>Tu acceso a STC Torneos quedó en estado <strong>{{ $user->statusLabel() }}</strong>.</p>
    @if ($user->status === 'suspended' && $user->suspension_reason)
        <p><strong>Motivo:</strong> {{ $user->suspension_reason }}</p>
    @endif
    @if ($user->status === 'active')
        <p>Ya podés ingresar con tu correo y contraseña.</p>
    @endif
@endcomponent
