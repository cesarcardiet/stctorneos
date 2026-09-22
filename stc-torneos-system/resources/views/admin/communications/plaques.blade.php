<x-layouts.stc
    title="Placas | STC Torneos"
    active="Comunicaciones"
    heading="Placas"
    subheading="Piezas visuales de resultados y contenido oficial para compartir."
>
    @include('admin.communications.partials.tabs')

    <section class="delegation-form-card">
        <h3>Generar placa automática</h3>
        <form method="post" action="{{ route('admin.communications.plaques.generate') }}">
            @csrf
            <div class="ficha-row">
                <label>Tipo
                    <select name="kind" required>
                        @foreach ($kinds as $value => $label)
                            <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </label>
                <label>Partido
                    <select name="match_id">
                        <option value="">Sin partido</option>
                        @foreach ($matches as $match)
                            <option value="{{ $match->id }}">{{ $match->title() }}</option>
                        @endforeach
                    </select>
                </label>
                <label>Categoría
                    <select name="category_id">
                        <option value="">Sin categoría</option>
                        @foreach ($categories as $category)
                            <option value="{{ $category->id }}">{{ $category->name }}</option>
                        @endforeach
                    </select>
                </label>
                <label>Fecha
                    <select name="round">
                        <option value="">Sin fecha</option>
                        @foreach ($rounds as $round)
                            <option value="{{ $round }}">{{ $round }}</option>
                        @endforeach
                    </select>
                </label>
            </div>
            <div class="ficha-row">
                <label>Jugador
                    <select name="player_id">
                        <option value="">Sin jugador</option>
                        @foreach ($players as $player)
                            <option value="{{ $player->id }}">{{ $player->fullName() }}</option>
                        @endforeach
                    </select>
                </label>
                <label>Titular
                    <select name="side">
                        <option value="home">Local</option>
                        <option value="away">Visitante</option>
                    </select>
                </label>
            </div>
            <button class="ficha-save" type="submit">Generar placa</button>
        </form>
    </section>

    <section class="results-rankings-grid">
        <article class="sheets-list-card">
            <h3>Placas de contenido</h3>
            <div class="delegation-figma-table comms-fav-table">
                <div class="table-head">
                    <span>Título</span>
                    <span>Estado</span>
                    <span>Acción</span>
                </div>
                @forelse ($posts as $post)
                    <div class="table-row">
                        <span><strong>{{ $post->title }}</strong></span>
                        <span>{{ $post->statusLabel() }}</span>
                        <span class="delegation-row-actions">
                            <a class="primary-action" href="{{ route('admin.communications.plaque', $post) }}" target="_blank" rel="noopener">Ver</a>
                        </span>
                    </div>
                @empty
                    <div class="category-empty-state"><strong>Creá un contenido de tipo placa para generar una pieza.</strong></div>
                @endforelse
            </div>
        </article>

        <article class="sheets-list-card">
            <h3>Placas de planillas publicadas</h3>
            <div class="delegation-figma-table comms-fav-table">
                <div class="table-head">
                    <span>Partido</span>
                    <span>Marcador</span>
                    <span>Acción</span>
                </div>
                @forelse ($sheets as $sheet)
                    <div class="table-row">
                        <span><strong>{{ $sheet->match?->title() }}</strong></span>
                        <span>{{ $sheet->scoreLine() }}</span>
                        <span class="delegation-row-actions">
                            <a class="primary-action" href="{{ route('admin.sheets.placa', $sheet) }}" target="_blank" rel="noopener">Ver</a>
                        </span>
                    </div>
                @empty
                    <div class="category-empty-state"><strong>Todavía no hay planillas publicadas.</strong></div>
                @endforelse
            </div>
        </article>
    </section>
</x-layouts.stc>
