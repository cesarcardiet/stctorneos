<form class="player-status-form" method="post" action="{{ route('admin.players.status', $player) }}">
    @csrf
    @method('PATCH')
    @foreach (['enabled' => 'Habilitar', 'approved' => 'Aprobar', 'observed' => 'Observar', 'pending' => 'Dejar pendiente', 'blocked' => 'Bloquear', 'rejected' => 'Rechazar'] as $value => $label)
        <button type="submit" name="status" value="{{ $value }}" @class(['primary-action' => $value === 'enabled', 'ghost-action' => $value !== 'enabled'])>{{ $label }}</button>
    @endforeach
</form>
