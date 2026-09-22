<x-layouts.stc
    title="Generador de Fixture | STC Torneos"
    active="Fixture"
    heading="Generador / Ajuste de Fixture"
    subheading="Parámetros para generar cruces, asignar canchas y resolver conflictos."
>
    @if (session('status'))
        <div class="system-alert">{{ session('status') }}</div>
    @endif

    <a class="back-link" href="{{ route('admin.fixture.index') }}">← Volver a la agenda</a>

    <form class="ficha-review-panel admin-figma-form" method="post" action="{{ route('admin.fixture.generate.store') }}">
        @csrf

        <h3>Parámetros de generación</h3>
        <div class="ficha-row">
            <label>Torneo
                <select name="tournament_id" id="generate-tournament" required>
                    @foreach ($tournaments as $tournament)
                        <option value="{{ $tournament->id }}">{{ $tournament->name }}</option>
                    @endforeach
                </select>
            </label>
            <label>Categoría
                <select name="category_id" id="generate-category" required>
                    @foreach ($categories as $category)
                        <option value="{{ $category->id }}" data-tournament="{{ $category->tournament_id }}">{{ $category->name }} · {{ $category->teams_count }} equipos</option>
                    @endforeach
                </select>
            </label>
            <label>Formato <input value="Grupos + eliminatorias" disabled></label>
            <label>Canchas disponibles <input value="{{ $fieldsCount }}" disabled></label>
        </div>
        <div class="ficha-row">
            <label>Tiempo entre partidos
                <select name="gap_minutes">
                    <option value="10">10 min</option>
                    <option value="15" selected>15 min</option>
                    <option value="20">20 min</option>
                    <option value="30">30 min</option>
                </select>
            </label>
            <label>Criterio prioridad
                <select name="priority">
                    <option value="delegation">Evitar choque delegación</option>
                    <option value="field">Cancha libre primero</option>
                    <option value="compact">Horario compacto</option>
                </select>
            </label>
            <x-ws-datetime class="span-2" name="start_at" label="Fecha de inicio" value="2026-12-14 09:00" />
            <label>Publicación
                <select name="published">
                    <option value="0">Borrador</option>
                    <option value="1">Publicar en app</option>
                </select>
            </label>
        </div>
        <div class="ficha-row">
            <label>Cruce
                <select name="scope">
                    <option value="group">Mismo grupo</option>
                    <option value="intergroup">Entre grupos</option>
                </select>
            </label>
            <label>Partidos
                <select name="legs">
                    <option value="ida">Solo ida</option>
                    <option value="ida_vuelta">Ida y vuelta</option>
                </select>
            </label>
        </div>

        <h3>Conflictos detectados</h3>
        @forelse ($conflicts as $conflict)
            <p class="ficha-flow">{{ $conflict['label'] }} · {{ $conflict['match']->title() }} / {{ $conflict['other']->title() }}</p>
        @empty
            <p class="ficha-flow">No hay choques de cancha, equipo, delegación ni árbitro en el fixture actual.</p>
        @endforelse

        <div class="admin-figma-form-actions">
            <a class="admin-figma-cancel" href="{{ route('admin.fixture.index') }}">CANCELAR</a>
            <button class="ficha-save" type="submit">Generar fixture</button>
        </div>
    </form>

    <script>
        const tournament = document.getElementById('generate-tournament');
        const category = document.getElementById('generate-category');

        const filterCategories = () => {
            const id = tournament?.value || '';
            [...(category?.options || [])].forEach((option) => {
                option.hidden = id !== '' && option.dataset.tournament && option.dataset.tournament !== id;
            });

            const current = [...(category?.options || [])].find((option) => option.value === category.value && !option.hidden);
            if (! current) {
                const first = [...(category?.options || [])].find((option) => option.value && !option.hidden);
                if (first) {
                    category.value = first.value;
                }
            }
        };

        tournament?.addEventListener('change', filterCategories);
        filterCategories();
    </script>
</x-layouts.stc>
