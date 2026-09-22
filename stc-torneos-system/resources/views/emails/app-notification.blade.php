@component('emails.layout', get_defined_vars())
    @if ($notification->tournament?->name)
        <p><strong>{{ $notification->tournament->name }}</strong></p>
    @endif
    @if ($notification->body)
        <p>{!! nl2br(e($notification->body)) !!}</p>
    @else
        <p>Tenés una nueva comunicación en STC Torneos.</p>
    @endif
@endcomponent
