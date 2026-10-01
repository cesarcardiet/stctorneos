@php
    $onField = $category->startersLimit();
    $saved = $match->lineups->keyBy('player_id');
    $starterSlots = ['home' => 0, 'away' => 0];
    $sides = [
        ['key' => 'home', 'team' => $match->homeTeam],
        ['key' => 'away', 'team' => $match->awayTeam],
    ];
@endphp

<x-ws-modal id="lineup" title="Alineación" class="is-lineup" data-ws-auto-open="{{ $errors->has('lineup') ? '1' : '0' }}">
    <form method="post" action="{{ route('workspace.categories.matches.lineup', [$category, $match]) }}" data-ws-lineup data-on-field="{{ $onField }}">
        @csrf
        <p class="ws-lineup-note">En cancha van <b>{{ $onField }}</b> titulares, según la modalidad. Si hay más jugadores, el resto queda como <b>suplente</b> y no se pueden marcar más titulares.</p>
        @error('lineup')
            <p class="ws-lineup-alert">{{ $message }}</p>
        @enderror

        <div class="ws-lineup-tabs" role="tablist">
            @foreach ($sides as $side)
                <button type="button" class="ws-lineup-tab {{ $loop->first ? 'is-on' : '' }}" data-lineup-tab="{{ $side['key'] }}" role="tab">
                    {{ $side['team']?->name ?: ($side['key'] === 'home' ? 'Local' : 'Visitante') }}
                </button>
            @endforeach
        </div>

        <div data-lineup-list>
            @foreach ($sides as $side)
                <div class="ws-lineup-panel" data-lineup-panel="{{ $side['key'] }}" @unless ($loop->first) hidden @endunless>
                    @forelse ($side['team']?->players ?? [] as $player)
                        @php
                            $row = $saved->get($player->id);
                            $sideKey = $side['key'];
                            $wantsStarter = $row ? (bool) $row->starter : true;
                            $starter = $wantsStarter && $starterSlots[$sideKey] < $onField;
                            if ($starter) {
                                $starterSlots[$sideKey]++;
                            }
                            $shirt = $row?->shirt_number ?? $player->jersey_number;
                        @endphp
                        <div
                            class="ws-lineup-row"
                            data-lineup-row
                            data-team-key="{{ $side['key'] }}"
                            data-player-id="{{ $player->id }}"
                            data-name="{{ $player->first_name ?: $player->fullName() }}"
                            data-photo="{{ $player->photoUrl() }}"
                            data-sort="{{ $row?->sort_order ?? 999 }}"
                        >
                            <label class="ws-lineup-check">
                                <input type="checkbox" data-called @checked($starter)>
                                <span>{{ $player->fullName() }}</span>
                            </label>
                            <input type="hidden" name="called[]" value="{{ $player->id }}">
                            <label class="ws-lineup-shirt">
                                <span>Nº de camiseta</span>
                                <input type="number" name="shirts[{{ $player->id }}]" min="0" max="99" inputmode="numeric" value="{{ $shirt }}" data-shirt>
                            </label>
                            <button type="button" class="ws-lineup-role {{ $starter ? 'is-starter' : 'is-sub' }}" data-role-toggle>
                                {{ $starter ? 'Titular' : 'Suplente' }}
                            </button>
                            <input type="hidden" name="roles[{{ $player->id }}]" value="{{ $starter ? 'starter' : 'sub' }}" data-role>
                            <input type="hidden" name="pos_x[{{ $player->id }}]" value="{{ $row?->pos_x }}" data-pos-x>
                            <input type="hidden" name="pos_y[{{ $player->id }}]" value="{{ $row?->pos_y }}" data-pos-y>
                        </div>
                    @empty
                        <p class="ws-lineup-note">Este equipo todavía no tiene jugadores en el plantel.</p>
                    @endforelse
                </div>
            @endforeach
        </div>

        <div class="ws-lineup-graphic" data-lineup-graphic hidden>
            <div class="ws-pitch" data-ws-pitch>
                <svg class="ws-pitch-lines" viewBox="0 0 68 105" preserveAspectRatio="none" aria-hidden="true">
                    <rect x="2" y="2" width="64" height="101" fill="none" stroke="#fff" stroke-width="0.6"/>
                    <line x1="2" y1="52.5" x2="66" y2="52.5" stroke="#fff" stroke-width="0.6"/>
                    <circle cx="34" cy="52.5" r="8" fill="none" stroke="#fff" stroke-width="0.6"/>
                    <rect x="14" y="2" width="40" height="16" fill="none" stroke="#fff" stroke-width="0.6"/>
                    <rect x="22" y="2" width="24" height="7" fill="none" stroke="#fff" stroke-width="0.6"/>
                    <rect x="14" y="87" width="40" height="16" fill="none" stroke="#fff" stroke-width="0.6"/>
                    <rect x="22" y="96" width="24" height="7" fill="none" stroke="#fff" stroke-width="0.6"/>
                </svg>
                <div class="ws-pitch-players" data-ws-pitch-players></div>
            </div>
            <p class="ws-lineup-note" data-pitch-empty hidden>Marcá titulares para ubicarlos en la cancha.</p>
        </div>

        <footer class="ws-lineup-foot">
            <button type="button" class="ws-lineup-link" data-lineup-graphic-open>Vista gráfica</button>
            <button type="button" class="ws-lineup-link is-muted" data-lineup-graphic-back hidden>Quitar</button>
            <button type="submit" class="ws-lineup-save">Guardar</button>
        </footer>
    </form>
</x-ws-modal>
