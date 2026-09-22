<form class="sheets-editor-card" method="post" action="{{ route('admin.sheets.datos.update', $sheet) }}">
    @csrf
    @method('PUT')

    <div class="fields-form-grid">
        <label class="fields-form-field">
            <span>Torneo</span>
            <input value="{{ $sheet->match?->tournament?->name }}" readonly>
        </label>
        <label class="fields-form-field">
            <span>Categoría</span>
            <input value="{{ $sheet->match?->category?->birth_year ?: $sheet->match?->category?->name }}" readonly>
        </label>
        <label class="fields-form-field span-2">
            <span>Partido</span>
            <input value="{{ $sheet->match?->title() }}" readonly>
        </label>
        <label class="fields-form-field">
            <span>Fecha y hora</span>
            <input type="datetime-local" name="scheduled_at" value="{{ $sheet->match?->scheduled_at?->format('Y-m-d\TH:i') }}" @disabled($sheet->locked)>
        </label>
        <label class="fields-form-field">
            <span>Cancha</span>
            <select name="field_id" @disabled($sheet->locked)>
                @foreach ($fields as $field)
                    <option value="{{ $field->id }}" @selected((int) $sheet->match?->field_id === (int) $field->id)>{{ $field->name }} · {{ $field->venue?->name }}</option>
                @endforeach
            </select>
        </label>
        <label class="fields-form-field">
            <span>Árbitro</span>
            <input name="referee_name" value="{{ old('referee_name', $sheet->referee_name) }}" placeholder="Martín Sosa" @disabled($sheet->locked)>
        </label>
        <label class="fields-form-field">
            <span>Asistente</span>
            <input name="assistant_name" value="{{ old('assistant_name', $sheet->assistant_name) }}" placeholder="Carla Pérez" @disabled($sheet->locked)>
        </label>
    </div>

    @unless ($sheet->locked)
        <div class="fields-form-actions">
            <button type="submit">Guardar</button>
            <button type="submit" name="continue" value="1">Continuar a eventos</button>
        </div>
    @endunless
</form>
