<x-layouts.stc
    title="Sanciones | STC Torneos"
    active="Resultados"
    heading="Sanciones y resoluciones"
    :subheading="$subheading"
>
    @if (session('status'))
        <div class="system-alert">{{ session('status') }}</div>
    @endif

    @include('admin.results.partials.tabs')

    <section class="delegation-form-card">
        <h3>Nueva resolución</h3>
        <form method="post" action="{{ route('admin.results.sanctions.store') }}">
            @csrf
            <div class="ficha-row">
                <label>Torneo
                    <select name="tournament_id" required>
                        @foreach ($tournaments as $tournament)
                            <option value="{{ $tournament->id }}">{{ $tournament->name }}</option>
                        @endforeach
                    </select>
                </label>
                <label>Categoría
                    <select name="category_id">
                        <option value="">Sin categoría</option>
                        @foreach ($categories as $item)
                            <option value="{{ $item->id }}">{{ $item->name }}</option>
                        @endforeach
                    </select>
                </label>
                <label>Equipo
                    <select name="team_id">
                        <option value="">Sin equipo</option>
                        @foreach ($teams as $team)
                            <option value="{{ $team->id }}">{{ $team->name }}</option>
                        @endforeach
                    </select>
                </label>
                <label>Tipo
                    <select name="type">
                        @foreach ($types as $value => $label)
                            <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </label>
            </div>
            <div class="ficha-row">
                <label>Título <input name="title" required placeholder="Descuento de 3 puntos"></label>
                <label>Impacto en tabla <input type="number" name="points_delta" value="-3"></label>
                <label>Estado
                    <select name="status">
                        @foreach (\App\Models\Sanction::statusLabels() as $value => $label)
                            <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </label>
                <label>Resolución <input name="resolution" placeholder="Acta / fundamento"></label>
            </div>
            <button class="ficha-save" type="submit">Registrar sanción</button>
        </form>
    </section>

    <section class="sheets-list-card" style="margin-top: 1.2rem;">
        <h3>Trazabilidad</h3>
        <div class="delegation-figma-table">
            <div class="table-head">
                <span>Título</span>
                <span>Sujeto</span>
                <span>Impacto</span>
                <span>Estado</span>
                <span>Acción</span>
            </div>
            @forelse ($sanctions as $sanction)
                <div class="table-row">
                    <span>
                        <strong>{{ $sanction->title }}</strong>
                        <small>{{ $sanction->typeLabel() }} · {{ $sanction->applied_at?->format('d/m/Y') }}</small>
                    </span>
                    <span>{{ $sanction->subjectLabel() }}</span>
                    <span>{{ $sanction->pointsLabel() }}</span>
                    <span>{{ $sanction->statusLabel() }}</span>
                    <span>
                        <form method="post" action="{{ route('admin.results.sanctions.update', $sanction) }}">
                            @csrf
                            @method('PATCH')
                            <input type="hidden" name="resolution" value="{{ $sanction->resolution }}">
                            <select name="status" onchange="this.form.submit()">
                                @foreach (\App\Models\Sanction::statusLabels() as $value => $label)
                                    <option value="{{ $value }}" @selected($sanction->status === $value)>{{ $label }}</option>
                                @endforeach
                            </select>
                        </form>
                    </span>
                </div>
            @empty
                <div class="category-empty-state"><strong>Todavía no hay sanciones ni resoluciones.</strong></div>
            @endforelse
        </div>
    </section>
</x-layouts.stc>
