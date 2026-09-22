<x-layouts.workspace
    title="Operación · Categorías"
    heading="Categorías"
    subheading="{{ auth()->user()?->restrictsToAssignedClub() ? 'Consulta pública: nombre, modalidad, formato y equipos.' : 'La misma categoría en todos los torneos. Entrá al que quieras operar.' }}"
    active="Categorías"
>
    <div class="ws-cat-board" data-ws-cat-board data-live-filter>
        <div class="ws-cat-tools">
            @include('workspace.partials.live-search', [
                'placeholder' => 'Sub 12, 2014, Santa Teresita…',
                'empty' => 'No hay categorías con esa búsqueda.',
            ])
            <div class="ws-cat-tools-row">
                @include('workspace.partials.category-az', ['letters' => $letters ?? []])
            </div>
        </div>

        <section class="ws-dir-stack">
            @forelse ($groups as $group)
                <article
                    class="ws-dir-group"
                    data-ws-cat-item
                    data-name="{{ $group['name'] }}"
                    data-letter="{{ \App\Models\Category::letterFromName($group['name']) }}"
                    data-search="{{ $group['name'] }} {{ $group['birth_year'] }} {{ $group['branch'] }} {{ $group['modality'] }}"
                >
                <header>
                    <div>
                        <strong>{{ $group['name'] }}</strong>
                        <p>{{ $group['birth_year'] }} · {{ $group['branch'] }} · {{ $group['modality'] }}</p>
                    </div>
                    <small>{{ $group['tournaments_count'] }} {{ $group['tournaments_count'] === 1 ? 'torneo' : 'torneos' }} · {{ $group['teams_count'] }} equipos</small>
                </header>
                <div class="ws-dir-rows">
                    @foreach ($group['categories'] as $item)
                        <a class="ws-dir-row" href="{{ route('workspace.categories.home', $item) }}">
                            <img src="{{ $item->bannerUrl() }}" alt="" data-ws-cat-live-img>
                            <div>
                                <strong>{{ $item->tournament?->name ?: 'Sin torneo' }}</strong>
                                @if (auth()->user()?->restrictsToAssignedClub())
                                    <span>{{ $item->modalityLabel() }} · {{ $item->competition_format ?: 'Sin formato' }} · {{ $item->teams_count }} equipos</span>
                                @else
                                    <span>{{ $item->statusLabel() }} · {{ $item->teams_count }} equipos · {{ $item->players_count }} jug. · {{ $item->matches_count }} partidos</span>
                                @endif
                            </div>
                            <b>Entrar</b>
                        </a>
                    @endforeach
                </div>
            </article>
            @empty
                <div class="ws-empty">
                    <strong>No hay categorías para mostrar.</strong>
                    <p>Cuando existan las mismas categorías en varios torneos, las vas a ver agrupadas acá.</p>
                </div>
            @endforelse
        </section>
    </div>
</x-layouts.workspace>
