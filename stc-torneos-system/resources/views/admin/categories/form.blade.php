<x-layouts.stc
    :title="$title.' | STC Torneos'"
    active="Categorías"
    :heading="$title"
    subheading="Reglas deportivas por edad, modalidad, cupos, fases y criterios de clasificación."
>
    <a class="back-link" href="{{ route('admin.categories.index') }}">← Volver al listado</a>

    @if ($errors->any())
        <div class="system-alert">Revisá los campos marcados antes de guardar.</div>
    @endif

    @php
        $modalities = \App\Models\Category::modalities();
        if ($category->modality && ! in_array($category->modality, $modalities, true)) {
            $modalities[] = $category->modality;
        }
    @endphp

    <form class="ficha-review-panel admin-figma-form" action="{{ $url }}" method="post" enctype="multipart/form-data">
        @csrf
        @if ($method === 'PUT')
            @method('PUT')
        @endif

        <h3>1. Datos</h3>
        <div class="ficha-row">
            <label>Torneo
                <select name="tournament_id">
                    @foreach ($tournaments as $tournament)
                        <option value="{{ $tournament->id }}" @selected((int) old('tournament_id', $category->tournament_id) === $tournament->id)>{{ $tournament->name }}</option>
                    @endforeach
                </select>
            </label>
            <label>Categoría <input name="name" value="{{ old('name', $category->name) }}" placeholder="Sub 12 Masculino"></label>
            <label>Año / rango <input name="birth_year" value="{{ old('birth_year', $category->birth_year) }}" placeholder="2014 o 2013/2014" inputmode="numeric" maxlength="9" pattern="\d{4}(/\d{4})?" title="Año de 4 dígitos (ej. 2014) o rango 2013/2014"></label>
            <label>Rama
                <select name="branch">
                    @foreach (['Masculina', 'Femenina', 'Mixta'] as $branch)
                        <option value="{{ $branch }}" @selected(old('branch', $category->branch) === $branch)>{{ $branch }}</option>
                    @endforeach
                </select>
            </label>
        </div>
        <div class="ficha-row">
            <label>Modalidad
                <select name="modality" id="category-modality">
                    @foreach ($modalities as $modality)
                        <option value="{{ $modality }}" @selected(old('modality', $category->modality) === $modality)>{{ $modality }}</option>
                    @endforeach
                </select>
            </label>
            <label>Modalidad personalizada
                <input name="custom_modality" value="{{ old('custom_modality', $category->custom_modality) }}" placeholder="Ej. Fútbol 8 reducido">
            </label>
            <label>Estado
                <select name="status">
                    @foreach (\App\Models\Category::statusLabels() as $value => $label)
                        <option value="{{ $value }}" @selected(old('status', $category->status) === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </label>
            <div class="span-2">
                @include('workspace.partials.category-banner-field', [
                    'src' => $category->bannerUrl(),
                    'logos' => $bannerLogos ?? [],
                    'currentPath' => old('image_path', $category->image_path),
                    'categoryId' => $category->id,
                    'identity' => $category->exists ? $category->identityKey() : null,
                ])
            </div>
        </div>

        <h3>2. Competencia</h3>
        <div class="ficha-row">
            <label>Mínimo jugadores <input type="number" name="min_players" value="{{ old('min_players', $category->min_players) }}"></label>
            <label>Máximo jugadores <input type="number" name="max_players" value="{{ old('max_players', $category->max_players) }}"></label>
            <label>Jugadores en cancha <input type="number" name="players_on_field" value="{{ old('players_on_field', $category->players_on_field) }}"></label>
            <label>Suplentes <input type="number" name="substitutes" value="{{ old('substitutes', $category->substitutes) }}"></label>
        </div>
        <div class="ficha-row">
            <label>Períodos <input type="number" name="periods" value="{{ old('periods', $category->periods) }}"></label>
            <label>Duración por período <input type="number" name="period_duration" value="{{ old('period_duration', $category->period_duration) }}"></label>
            <label>Cupo de equipos <input type="number" name="team_limit" value="{{ old('team_limit', $category->team_limit) }}"></label>
        </div>
        @include('partials.competition-format-fields', [
            'competitionFormat' => $category->competition_format,
            'groupsCount' => $category->groups_count,
            'teamsPerGroup' => $category->teams_per_group ?? 4,
        ])
        <div class="ficha-row">
            <label>Clasificados <input type="number" name="qualifiers_count" value="{{ old('qualifiers_count', $category->qualifiers_count) }}"></label>
            <label>Fases / llaves <input name="phases" value="{{ old('phases', $category->phases) }}" placeholder="Grupos, octavos, cuartos, semis, final"></label>
        </div>
        <div class="ficha-row">
            <label class="span-2">Cruces / llaves <textarea name="brackets" rows="4" placeholder="Cómo se arman los cruces y las llaves">{{ old('brackets', $category->brackets) }}</textarea></label>
            <label class="span-2">Criterios de clasificación <textarea name="classification_criteria" rows="4" placeholder="Quiénes pasan de fase y con qué orden">{{ old('classification_criteria', $category->classification_criteria) }}</textarea></label>
        </div>
        <div class="ficha-row">
            <label>Puntos victoria <input type="number" name="points_win" value="{{ old('points_win', $category->points_win) }}"></label>
            <label>Puntos empate <input type="number" name="points_draw" value="{{ old('points_draw', $category->points_draw) }}"></label>
            <label>Puntos derrota <input type="number" name="points_loss" value="{{ old('points_loss', $category->points_loss) }}"></label>
        </div>

        <h3>3. Reglas</h3>
        <div class="ficha-row">
            <label class="span-2">Criterios de desempate <textarea name="tiebreakers" rows="4">{{ old('tiebreakers', implode("\n", $category->tiebreakers ?? [])) }}</textarea></label>
            <label class="span-2">Reglas aplicables <textarea name="rules" rows="4">{{ old('rules', $category->rules) }}</textarea></label>
        </div>
        <div class="ficha-row">
            <label>Fair Play amarilla <input type="number" name="fair_play_yellow" min="0" max="10" value="{{ old('fair_play_yellow', $category->fair_play_yellow ?? 1) }}"></label>
            <label>Fair Play roja <input type="number" name="fair_play_red" min="0" max="10" value="{{ old('fair_play_red', $category->fair_play_red ?? 3) }}"></label>
            <label>Fair Play incidencia <input type="number" name="fair_play_incident" min="0" max="10" value="{{ old('fair_play_incident', $category->fair_play_incident ?? 2) }}"></label>
        </div>
        <div class="ficha-row">
            <label class="span-4">Disciplina <textarea name="discipline_rules" rows="4">{{ old('discipline_rules', $category->discipline_rules) }}</textarea></label>
        </div>

        <div class="admin-figma-form-actions">
            <a class="admin-figma-cancel" href="{{ route('admin.categories.index') }}">CANCELAR</a>
            <button class="ficha-save" type="submit">Guardar categoría</button>
        </div>
    </form>
</x-layouts.stc>
