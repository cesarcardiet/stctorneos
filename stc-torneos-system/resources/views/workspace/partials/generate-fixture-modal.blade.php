@php
    $fixtureGroups = collect($teams ?? [])
        ->groupBy(fn ($team) => strtoupper(trim((string) $team->group_name)) ?: '—')
        ->sortKeys();
    $oddGroups = $fixtureGroups->filter(fn ($groupTeams) => $groupTeams->count() % 2 === 1);
@endphp

<x-ws-modal id="generate-fixture" title="Generar partidos" :wide="true">
    <x-ws-form :action="route('workspace.categories.fixture.generate', $category)" submit="Generar">
        <p class="ws-muted">Arma los cruces de la categoría. No pisa partidos que ya existen.</p>

        @if ($fixtureGroups->isNotEmpty())
            <div class="ws-fixture-preview">
                <p class="stc-eyebrow">Equipos por grupo</p>
                <ul>
                    @foreach ($fixtureGroups as $letter => $groupTeams)
                        <li>
                            <strong>{{ $category->groupDisplayName($letter) }}</strong>
                            · {{ $groupTeams->count() }} equipos
                            @if ($groupTeams->count() % 2 === 1)
                                · en cada fecha queda 1 libre
                            @endif
                        </li>
                    @endforeach
                </ul>
            </div>
        @endif

        <label>Cruce
            <select name="scope" required data-ws-fixture-scope>
                <option value="group">Mismo grupo</option>
                <option value="intergroup">Interzonales (entre grupos)</option>
            </select>
        </label>

        <div class="ws-fixture-hint" data-ws-scope-hint="group">
            Cada grupo juega entre sí. Un grupo de 4 arma 2 partidos por fecha; un grupo de 3 arma 1 y queda un equipo libre. Ese libre se cruza, en cada fecha, con el libre de la zona mezclada (C con D, E con F…).
        </div>
        <div class="ws-fixture-hint" data-ws-scope-hint="intergroup" hidden>
            Los partidos se arman solo entre las zonas mezcladas: C contra D, E contra F, y así. Podés cambiarlo a mano.
        </div>

        @php
            $mixLetters = $category->mixPairLetters();
            $defaultMix = $category->defaultMixPairs();
        @endphp

        @if (count($mixLetters) >= 2)
            <div class="ws-fixture-preview" data-ws-mix-box>
                <p class="stc-eyebrow">Mezcla manual de zonas</p>
                <p class="ws-muted">Por defecto A con B, C con D, E con F. El que queda libre en un grupo juega, en cada fecha, con el que queda libre del grupo mezclado: el libre de C enfrenta al libre de D, el de E al de F, y así.</p>
                <div class="ws-mix-rows">
                    @foreach ($mixLetters as $from)
                        <label>{{ $category->groupDisplayName($from) }} se mezcla con
                            <select name="mix_pairs[{{ $from }}]" data-ws-mix-from="{{ $from }}">
                                <option value="">Ninguno</option>
                                @foreach ($mixLetters as $to)
                                    @if ($to !== $from)
                                        <option value="{{ $to }}" @selected(($defaultMix[$from] ?? '') === $to)>{{ $category->groupDisplayName($to) }}</option>
                                    @endif
                                @endforeach
                            </select>
                        </label>
                    @endforeach
                </div>
            </div>
        @endif

        @if ($oddGroups->isNotEmpty())
            <div class="ws-warn-box" data-ws-bye-warning>
                <p><strong>Hay grupos con cantidad impar.</strong> En cada fecha esos equipos no tienen rival dentro de su zona:</p>
                <ul>
                    @foreach ($oddGroups as $letter => $groupTeams)
                        <li>{{ $category->groupDisplayName($letter) }} ({{ $groupTeams->count() }}): {{ $groupTeams->pluck('name')->join(', ') }}</li>
                    @endforeach
                </ul>
                <label class="ws-check">
                    <input type="hidden" name="fill_byes" value="0">
                    <input type="checkbox" name="fill_byes" value="1" checked>
                    Completar cada fecha: el libre de cada grupo juega con el libre de la zona mezclada
                </label>
                <p class="ws-muted">Si C se mezcla con D y E con F, nadie queda libre. Solo queda sin rival un grupo que no tenga zona mezclada.</p>
            </div>
        @else
            <input type="hidden" name="fill_byes" value="1">
        @endif

        <label>Partidos
            <select name="legs" required>
                <option value="ida">Solo ida</option>
                <option value="ida_vuelta">Ida y vuelta</option>
            </select>
        </label>
        <label>Fase
            <select name="stage">
                @foreach ($phases as $phase)
                    <option value="{{ $phase }}" @selected(($selectedPhase ?? '') === $phase || (($selectedPhase ?? 'all') === 'all' && $loop->first))>{{ $phase }}</option>
                @endforeach
            </select>
        </label>
        <x-ws-datetime name="start_at" label="Fecha de inicio" :value="$defaultStart ?? null" />
        <label>Tiempo entre partidos
            <select name="gap_minutes">
                <option value="10">10 min</option>
                <option value="15" selected>15 min</option>
                <option value="20">20 min</option>
                <option value="30">30 min</option>
            </select>
        </label>
        <label>Criterio
            <select name="priority">
                <option value="delegation">Evitar choque de delegación</option>
                <option value="field">Cancha libre primero</option>
                <option value="compact">Horario compacto</option>
            </select>
        </label>
        <label>Publicación
            <select name="published">
                <option value="0">Borrador</option>
                <option value="1">Publicar en app</option>
            </select>
        </label>
    </x-ws-form>
</x-ws-modal>
