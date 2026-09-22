<form class="sheets-editor-card" method="post" action="{{ route('admin.sheets.incidencias.store', $sheet) }}">
    @csrf

    <div class="fields-form-grid">
        <label class="fields-form-field span-2">
            <span>Tipo Fair Play STC</span>
            <select name="fair_play_kind" @disabled($sheet->locked)>
                <option value="">Detectar por texto</option>
                @foreach (\App\Models\MatchSheetIncident::fairPlayKindOptions() as $value => $label)
                    <option value="{{ $value }}" @selected(old('fair_play_kind') === $value)>{{ $label }} (+{{ \App\Support\FairPlayRules::scale()[$value] ?? '—' }})</option>
                @endforeach
            </select>
        </label>
        <label class="fields-form-field span-2">
            <span>Incidencia principal</span>
            <input name="incident_title" value="{{ old('incident_title', $sheet->incident_title) }}" placeholder="Conducta inapropiada de un padre" @disabled($sheet->locked)>
        </label>
        <label class="fields-form-field">
            <span>Equipo relacionado</span>
            <select name="incident_team_id" @disabled($sheet->locked)>
                <option value="">Sin equipo</option>
                @if ($sheet->match?->homeTeam)
                    <option value="{{ $sheet->match->homeTeam->id }}" @selected((int) old('incident_team_id', $sheet->incident_team_id) === (int) $sheet->match->homeTeam->id)>{{ $sheet->match->homeTeam->name }}</option>
                @endif
                @if ($sheet->match?->awayTeam)
                    <option value="{{ $sheet->match->awayTeam->id }}" @selected((int) old('incident_team_id', $sheet->incident_team_id) === (int) $sheet->match->awayTeam->id)>{{ $sheet->match->awayTeam->name }}</option>
                @endif
            </select>
        </label>
        <label class="fields-form-field">
            <span>Minuto / momento</span>
            <input name="incident_moment" value="{{ old('incident_moment', $sheet->incident_moment) }}" placeholder="Segundo tiempo" @disabled($sheet->locked)>
        </label>
        <label class="fields-form-field span-2">
            <span>Observaciones</span>
            <input name="incident_notes" value="{{ old('incident_notes', $sheet->incident_notes) }}" placeholder="Se dejó constancia para Fair Play y disciplina." @disabled($sheet->locked)>
        </label>
    </div>

    <div class="sheets-sign-card">
        @foreach ($sheet->signatures as $signature)
            <div class="sheets-sign-row">
                <span @class(['is-signed' => $signature->signed, 'is-pending' => ! $signature->signed])>{{ $signature->statusPhrase() }}</span>
                @if (! $signature->signed && ! $sheet->locked)
                    <button form="sign-{{ $signature->id }}" type="submit">Firmar</button>
                @endif
            </div>
        @endforeach
    </div>

    @unless ($sheet->locked)
        <div class="fields-form-actions">
            <button type="submit">Guardar</button>
            <button type="submit" name="continue" value="1">Continuar a revisión</button>
        </div>
    @endunless
</form>

@foreach ($sheet->signatures as $signature)
    @if (! $signature->signed && ! $sheet->locked)
        <form id="sign-{{ $signature->id }}" method="post" action="{{ route('admin.sheets.firmas.sign', [$sheet, $signature]) }}">
            @csrf
            @method('PATCH')
        </form>
    @endif
@endforeach
