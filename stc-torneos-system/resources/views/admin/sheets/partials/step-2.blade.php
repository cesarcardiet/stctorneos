<form class="sheets-editor-card" method="post" action="{{ route('admin.sheets.eventos.store', $sheet) }}">
    @csrf

    <div class="sheets-event-types">
        @foreach ($eventTypes as $value => $label)
            <label @class(['sheets-event-type', 'is-goal' => $value === 'goal', 'is-yellow' => $value === 'yellow', 'is-red' => $value === 'red'])>
                <input type="radio" name="type" value="{{ $value }}" @checked(old('type', 'goal') === $value) @disabled($sheet->locked)>
                <span>{{ $label }}</span>
            </label>
        @endforeach
        @unless ($sheet->locked)
            @if ($sheet->events->isNotEmpty())
                <button class="sheets-event-type is-void" form="void-event" type="submit">Anular</button>
            @endif
        @endunless
    </div>

    <div class="fields-form-grid">
        <label class="fields-form-field">
            <span>Jugador seleccionado</span>
            <select name="player_id" @disabled($sheet->locked)>
                <option value="">Sin jugador</option>
                @foreach ($players as $player)
                    <option value="{{ $player->id }}" @selected((int) old('player_id') === (int) $player->id)>{{ $player->fullName() }} · {{ $player->team?->name }}</option>
                @endforeach
            </select>
        </label>
        <label class="fields-form-field">
            <span>Minuto</span>
            <input type="number" name="minute" value="{{ old('minute', 31) }}" min="0" max="130" @disabled($sheet->locked)>
        </label>
        <label class="fields-form-field span-2">
            <span>Detalle</span>
            <input name="detail" value="{{ old('detail') }}" placeholder="Gol de jugada, asistencia de Ruiz" @disabled($sheet->locked)>
        </label>
    </div>

    @unless ($sheet->locked)
        <div class="fields-form-actions">
            <button type="submit">Agregar evento</button>
            <a href="{{ route('admin.sheets.incidencias', $sheet) }}">Continuar a incidencias</a>
        </div>
    @endunless
</form>

@if ($sheet->events->isNotEmpty() && ! $sheet->locked)
    <form id="void-event" method="post" action="{{ route('admin.sheets.eventos.destroy', [$sheet, $sheet->events->last()]) }}">
        @csrf
        @method('DELETE')
    </form>
@endif
