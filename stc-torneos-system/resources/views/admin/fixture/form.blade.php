<x-layouts.stc
    :title="$title.' | STC Torneos'"
    active="Fixture"
    :heading="$title"
    subheading="Programación de cruce con torneo, categoría, equipos y cancha vinculados."
>
    <a class="back-link" href="{{ route('admin.fixture.index') }}">← Volver a la agenda</a>

    @if ($errors->any())
        <div class="system-alert">Revisá los campos marcados. Local y visitante tienen que ser de la misma categoría del torneo.</div>
    @endif

    <form class="ficha-review-panel admin-figma-form" method="post" action="{{ $url }}">
        @csrf
        @if ($method === 'PUT')
            @method('PUT')
        @endif

        <h3>Datos del partido</h3>
        <div class="ficha-row">
            <label>Torneo
                <select name="tournament_id" id="fixture-tournament" required>
                    @foreach ($tournaments as $tournament)
                        <option value="{{ $tournament->id }}" @selected((int) old('tournament_id', $match->tournament_id) === $tournament->id)>{{ $tournament->name }}</option>
                    @endforeach
                </select>
            </label>
            <label>Categoría
                <select name="category_id" id="fixture-category" required>
                    @foreach ($categories as $category)
                        <option value="{{ $category->id }}" data-tournament="{{ $category->tournament_id }}" @selected((int) old('category_id', $match->category_id) === $category->id)>{{ $category->name }}</option>
                    @endforeach
                </select>
            </label>
            <label>Cancha
                <select name="field_id" id="fixture-field" required>
                    @foreach ($fields as $field)
                        <option
                            value="{{ $field->id }}"
                            data-tournament="{{ $field->venue?->tournament_id }}"
                            @selected((int) old('field_id', $match->field_id) === $field->id)
                        >{{ $field->name }} · {{ $field->venue?->name }}</option>
                    @endforeach
                </select>
            </label>
            <label>Estado
                <select name="status">
                    @foreach (\App\Models\FixtureMatch::statusLabels() as $value => $label)
                        <option value="{{ $value }}" @selected(old('status', $match->status) === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </label>
        </div>

        <div class="ficha-row">
            <label>Equipo local
                <select name="home_team_id" class="fixture-team" required>
                    @foreach ($teams as $team)
                        <option
                            value="{{ $team->id }}"
                            data-category="{{ $team->category_id }}"
                            data-tournament="{{ $team->tournament_id }}"
                            @selected((int) old('home_team_id', $match->home_team_id) === $team->id)
                        >
                            {{ $team->name }} · {{ $team->category?->name }}
                        </option>
                    @endforeach
                </select>
            </label>
            <label>Equipo visitante
                <select name="away_team_id" class="fixture-team" required>
                    @foreach ($teams as $team)
                        <option
                            value="{{ $team->id }}"
                            data-category="{{ $team->category_id }}"
                            data-tournament="{{ $team->tournament_id }}"
                            @selected((int) old('away_team_id', $match->away_team_id) === $team->id)
                        >
                            {{ $team->name }} · {{ $team->category?->name }}
                        </option>
                    @endforeach
                </select>
            </label>
            <x-ws-datetime class="span-2" name="scheduled_at" label="Día y hora" :value="old('scheduled_at', $match->scheduled_at)" />
            <label>Duración (min)
                <input type="number" name="duration_minutes" value="{{ old('duration_minutes', $match->duration_minutes ?: 70) }}">
            </label>
        </div>

        <div class="ficha-row">
            <label>Fase / grupo <input name="stage" value="{{ old('stage', $match->stage) }}" placeholder="Grupo A"></label>
            <label>Fecha del fixture <input name="round" value="{{ old('round', $match->round) }}" placeholder="Fecha 1"></label>
            <label>Árbitro (usuario)
                <select name="referee_user_id">
                    <option value="">Sin usuario</option>
                    @foreach ($officials ?? [] as $official)
                        <option value="{{ $official->id }}" @selected((int) old('referee_user_id', $match->referee_user_id) === $official->id)>{{ $official->name }} · {{ $official->roleLabel() }}</option>
                    @endforeach
                </select>
            </label>
            <label>Árbitro (texto) <input name="referee_name" value="{{ old('referee_name', $match->referee_name) }}" placeholder="Martín Sosa"></label>
            <label>Publicación
                <select name="published">
                    <option value="0" @selected(! old('published', $match->published))>Borrador</option>
                    <option value="1" @selected(old('published', $match->published))>Publicado en app</option>
                </select>
            </label>
            <label>Asistente
                <select name="assistant_user_id">
                    <option value="">Sin asignar</option>
                    @foreach ($officials ?? [] as $official)
                        <option value="{{ $official->id }}" @selected((int) old('assistant_user_id', $match->assistant_user_id) === $official->id)>{{ $official->name }}</option>
                    @endforeach
                </select>
            </label>
        </div>

        <div class="ficha-row">
            <label>Goles local <input type="number" name="home_score" value="{{ old('home_score', $match->home_score) }}"></label>
            <label>Goles visitante <input type="number" name="away_score" value="{{ old('away_score', $match->away_score) }}"></label>
            <label>Minuto <input name="minute" value="{{ old('minute', $match->minute) }}" placeholder="31:42"></label>
            <label>Pasa el ganador a
                <select name="next_match_id">
                    <option value="">Sin cruce siguiente</option>
                    @foreach ($knockoutMatches ?? [] as $next)
                        <option value="{{ $next->id }}" @selected((int) old('next_match_id', $match->next_match_id) === (int) $next->id)>{{ $next->title() }} · {{ $next->stage }}</option>
                    @endforeach
                </select>
            </label>
            <label>Como
                <select name="next_slot">
                    <option value="home" @selected(old('next_slot', $match->next_slot) === 'home')>Local</option>
                    <option value="away" @selected(old('next_slot', $match->next_slot) === 'away')>Visitante</option>
                </select>
            </label>
        </div>

        <div class="ficha-row">
            <label class="span-4">Notas de programación <textarea name="notes" rows="3">{{ old('notes', $match->notes) }}</textarea></label>
        </div>

        <div class="admin-figma-form-actions">
            <a class="admin-figma-cancel" href="{{ route('admin.fixture.index') }}">CANCELAR</a>
            <button class="ficha-save" type="submit">Guardar partido</button>
        </div>
    </form>

    <script>
        const tournament = document.getElementById('fixture-tournament');
        const category = document.getElementById('fixture-category');
        const field = document.getElementById('fixture-field');
        const teamSelects = document.querySelectorAll('.fixture-team');

        const visibleValue = (select) => {
            const current = [...select.options].find((option) => option.value === select.value && !option.hidden);
            if (current) {
                return;
            }

            const first = [...select.options].find((option) => option.value && !option.hidden);
            if (first) {
                select.value = first.value;
            }
        };

        const filterByTournament = () => {
            const id = tournament?.value || '';

            [...(category?.options || [])].forEach((option) => {
                option.hidden = id !== '' && option.dataset.tournament && option.dataset.tournament !== id;
            });

            [...(field?.options || [])].forEach((option) => {
                option.hidden = id !== '' && option.dataset.tournament && option.dataset.tournament !== id;
            });

            if (category) {
                visibleValue(category);
            }
            if (field) {
                visibleValue(field);
            }
            filterTeams();
        };

        const filterTeams = () => {
            const tournamentId = tournament?.value || '';
            const categoryId = category?.value || '';

            teamSelects.forEach((select) => {
                [...select.options].forEach((option) => {
                    const otherTournament = tournamentId !== '' && option.dataset.tournament && option.dataset.tournament !== tournamentId;
                    const otherCategory = categoryId !== '' && option.dataset.category && option.dataset.category !== categoryId;
                    option.hidden = Boolean(otherTournament || otherCategory);
                });
                visibleValue(select);
            });
        };

        tournament?.addEventListener('change', filterByTournament);
        category?.addEventListener('change', filterTeams);
        filterByTournament();
    </script>
</x-layouts.stc>
