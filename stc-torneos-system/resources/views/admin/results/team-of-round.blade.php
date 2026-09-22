<x-layouts.stc
    title="Equipo de la Fecha | STC Torneos"
    active="Resultados"
    heading="Equipo de la Fecha"
    :subheading="$subheading"
>
    @if (session('status'))
        <div class="system-alert">{{ session('status') }}</div>
    @endif

    @include('admin.results.partials.tabs')

    <form class="category-filter-bar results-filter-bar" method="get" action="{{ route('admin.results.team') }}">
        <input type="hidden" name="category_id" value="{{ $category?->id }}">
        <input type="hidden" name="tournament_id" value="{{ $filters['tournament_id'] }}">
        <select name="round">
            @foreach ($rounds as $item)
                <option value="{{ $item }}" @selected($round === (string) $item)>{{ $item }}</option>
            @endforeach
        </select>
        <button type="submit">Ver propuesta</button>
    </form>

    @if ($category && $round !== '')
        <form method="post" action="{{ route('admin.results.team.approve', ['category_id' => $category->id, 'round' => $round]) }}" data-confirm="¿Aprobar el Equipo de la Fecha según el STC Rating?">
            @csrf
            <input type="hidden" name="category_id" value="{{ $category->id }}">
            <input type="hidden" name="round" value="{{ $round }}">
            <button class="ficha-save" type="submit">Aprobar Equipo de la Fecha</button>
        </form>
    @endif

    <div class="results-rankings-grid" style="margin-top: 1.2rem;">
        <article class="sheets-list-card">
            <h3>Propuesta por rating</h3>
            <div class="delegation-figma-table">
                <div class="table-head"><span>#</span><span>Jugador</span><span>Rating</span></div>
                @forelse ($proposal as $row)
                    <div class="table-row">
                        <span>{{ $loop->iteration }}</span>
                        <span>
                            <x-entity-cell
                                :href="$row['player'] ? route('admin.players.show', $row['player']) : null"
                                :src="$row['player']?->listPhotoUrl() ?? asset('images/defaults/player.svg')"
                                :alt="$row['player']?->fullName() ?? 'Jugador'"
                                shape="round"
                            >
                                {{ $row['player']->fullName() }}
                                <x-slot:subtitle>{{ $row['team'] }}</x-slot:subtitle>
                            </x-entity-cell>
                        </span>
                        <span>{{ $row['points'] }}</span>
                    </div>
                @empty
                    <div class="category-empty-state"><strong>No hay once para esa fecha.</strong></div>
                @endforelse
            </div>
        </article>
        <article class="sheets-list-card">
            <h3>Aprobado</h3>
            <div class="delegation-figma-table">
                <div class="table-head"><span>#</span><span>Jugador</span><span>Rating</span></div>
                @forelse ($approved as $row)
                    <div class="table-row">
                        <span>{{ $row->sort_order }}</span>
                        <span>
                            <x-entity-cell
                                :href="$row->player ? route('admin.players.show', $row->player) : null"
                                :src="$row->player?->listPhotoUrl() ?? asset('images/defaults/player.svg')"
                                :alt="$row->player?->fullName() ?? 'Jugador'"
                                shape="round"
                            >
                                {{ $row->player?->fullName() }}
                                <x-slot:subtitle>{{ $row->team?->name }}</x-slot:subtitle>
                            </x-entity-cell>
                        </span>
                        <span>
                            {{ $row->rating }}
                            <form method="post" action="{{ route('admin.results.team.replace', $row) }}" style="margin-top:.35rem;">
                                @csrf
                                @method('PATCH')
                                <input type="hidden" name="category_id" value="{{ $category->id }}">
                                <input type="hidden" name="round" value="{{ $round }}">
                                <select name="player_id" required>
                                    @foreach ($candidates as $player)
                                        <option value="{{ $player->id }}" @selected((int) $row->player_id === (int) $player->id)>{{ $player->fullName() }} · {{ $player->team?->name }}</option>
                                    @endforeach
                                </select>
                                <button type="submit">Sustituir</button>
                            </form>
                        </span>
                    </div>
                @empty
                    <div class="category-empty-state"><strong>Todavía no está aprobado.</strong></div>
                @endforelse
            </div>
        </article>
    </div>
</x-layouts.stc>
