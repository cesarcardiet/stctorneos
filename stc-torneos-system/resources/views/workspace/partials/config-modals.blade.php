@php
    $ws = $workspace;
    $can = $canEdit ?? false;
@endphp

<x-ws-modal id="groups" title="Grupos">
    <x-ws-form :action="route('workspace.categories.settings.update', $category)" section="groups">
        <label>Número de grupos
            <input type="number" name="groups_count" min="1" max="{{ \App\Models\Category::MAX_GROUPS }}" value="{{ $category->groups_count }}">
        </label>
        <label>Asignación
            <select name="assign">
                <option value="keep">Mantener equipos</option>
                <option value="random">Repartir al azar</option>
            </select>
        </label>
        <p class="ws-muted">Los grupos se van a ver en Inicio y Clasificación, aunque todavía no tengan equipos.</p>
        <p class="stc-eyebrow">Nombre de cada grupo</p>
        @foreach ($category->groupLetters() as $letter)
            <label>Grupo {{ $letter }}
                <input type="text" name="group_names[{{ $letter }}]" value="{{ $category->groupCustomName($letter) }}" placeholder="Grupo {{ $letter }}" maxlength="40">
            </label>
        @endforeach
    </x-ws-form>
</x-ws-modal>

<x-ws-modal id="phases" title="Fases">
    <x-ws-form :action="route('workspace.categories.settings.update', $category)" section="phases">
        @foreach ($ws['phases'] as $index => $phase)
            <div class="ws-inline">
                <input type="hidden" name="phases[{{ $index }}][old]" value="{{ $phase['name'] }}">
                <label>Nombre <input type="text" name="phases[{{ $index }}][name]" value="{{ $phase['name'] }}"></label>
                <label>Modo
                    <select name="phases[{{ $index }}][mode]">
                        <option value="Todos contra Todos" @selected(($phase['mode'] ?? '') === 'Todos contra Todos')>Todos contra Todos</option>
                        <option value="Eliminatoria" @selected(($phase['mode'] ?? '') === 'Eliminatoria')>Eliminatoria</option>
                    </select>
                </label>
            </div>
        @endforeach
        <div class="ws-inline">
            <label>Nueva fase <input type="text" name="phases[{{ count($ws['phases']) }}][name]" placeholder="3º Fase"></label>
            <label>Modo
                <select name="phases[{{ count($ws['phases']) }}][mode]">
                    <option value="Todos contra Todos">Todos contra Todos</option>
                    <option value="Eliminatoria">Eliminatoria</option>
                </select>
            </label>
        </div>
    </x-ws-form>
</x-ws-modal>

<x-ws-modal id="rounds" title="Fechas">
    <x-ws-form :action="route('workspace.categories.settings.update', $category)" section="rounds">
        @foreach (\App\Support\CategoryWorkspace::editableRounds($ws) as $index => $round)
            <label>
                Nombre
                <input type="hidden" name="rounds[{{ $index }}][old]" value="{{ $round }}">
                <input type="text" name="rounds[{{ $index }}][name]" value="{{ $round }}">
            </label>
        @endforeach
        <label>Nueva fecha <input type="text" name="rounds[{{ count(\App\Support\CategoryWorkspace::editableRounds($ws)) }}][name]" placeholder="Octavos de final"></label>
        <p class="ws-muted">Podés dejar Fecha 1, 2, 3 o cambiarla a Octavos, Cuartos, Semifinal o Final.</p>
    </x-ws-form>
</x-ws-modal>

<x-ws-modal id="columns" title="Columnas de tabla">
    <x-ws-form :action="route('workspace.categories.settings.update', $category)" section="columns">
        @foreach ($columnLabels as $key => $label)
            <label class="ws-check">
                <input type="checkbox" name="columns[]" value="{{ $key }}" @checked(in_array($key, $ws['visible_columns'], true))>
                {{ $label }}
            </label>
        @endforeach
    </x-ws-form>
</x-ws-modal>

<x-ws-modal id="criteria" title="Criterios de clasificación">
    <x-ws-form :action="route('workspace.categories.settings.update', $category)" section="criteria">
        <p class="ws-muted">Arrastrá para cambiar el orden.</p>
        <ul class="ws-drag" data-ws-sort>
            @foreach ($ws['criteria'] as $item)
                <li>
                    <span>{{ $item }}</span>
                    <input type="hidden" name="criteria[]" value="{{ $item }}">
                </li>
            @endforeach
        </ul>
    </x-ws-form>
</x-ws-modal>

<x-ws-modal id="rules" title="Reglas del campeonato">
    <x-ws-form :action="route('workspace.categories.settings.update', $category)" section="rules">
        <label>Reglas
            <textarea name="rules" rows="8">{{ $category->rules }}</textarea>
        </label>
    </x-ws-form>
</x-ws-modal>

<x-ws-modal id="prizes" title="Premios">
    <x-ws-form :action="route('workspace.categories.settings.update', $category)" section="prizes">
        <label>1º Puesto <input type="text" name="first" value="{{ $ws['prizes']['first'] }}"></label>
        <label>2º Puesto <input type="text" name="second" value="{{ $ws['prizes']['second'] }}"></label>
        <label>3º Puesto <input type="text" name="third" value="{{ $ws['prizes']['third'] }}"></label>
        <label>Otros <input type="text" name="other" value="{{ $ws['prizes']['other'] }}"></label>
    </x-ws-form>
</x-ws-modal>

<x-ws-modal id="sport" title="Ajustes deportivos">
    <x-ws-form :action="route('workspace.categories.settings.update', $category)" section="sport">
        <label>Puntos de victoria <input type="number" name="points_win" value="{{ $category->points_win }}"></label>
        <label>Puntos por empate <input type="number" name="points_draw" value="{{ $category->points_draw }}"></label>
        <label>Puntos por derrota <input type="number" name="points_loss" value="{{ $category->points_loss }}"></label>
    </x-ws-form>
</x-ws-modal>

<x-ws-modal id="result" title="Resultado del campeonato">
    <x-ws-form :action="route('workspace.categories.settings.update', $category)" section="result">
        <label>Resaltar primeros <input type="number" name="highlight_first" min="0" value="{{ $ws['highlight_first'] }}"></label>
        <label>Resaltar últimos <input type="number" name="highlight_last" min="0" value="{{ $ws['highlight_last'] }}"></label>
    </x-ws-form>
</x-ws-modal>

<x-ws-modal id="registrations" title="Editar inscripciones">
    <x-ws-form :action="route('workspace.categories.settings.update', $category)" section="registrations">
        <label class="ws-check">
            <input type="hidden" name="registrations_open" value="0">
            <input type="checkbox" name="registrations_open" value="1" @checked($ws['registrations_open'])>
            Inscripciones abiertas
        </label>
        <p class="ws-muted">Si están cerradas, solo administración puede agregar equipos.</p>
        <label>Información de inscripción
            <textarea name="registration_info" rows="4">{{ $ws['registration_info'] }}</textarea>
        </label>
        <p class="ws-muted"><a href="{{ route('workspace.categories.inscriptions', $category) }}">Ver bandeja de inscripciones</a></p>
    </x-ws-form>
</x-ws-modal>

<x-ws-modal id="official" title="Arbitraje">
    <x-ws-form :action="route('workspace.categories.settings.update', $category)" section="official" submit="Agregar">
        <label>Nombre <input type="text" name="name" required></label>
        <label>Función <input type="text" name="role" placeholder="Árbitro"></label>
    </x-ws-form>
    @if (! empty($ws['officials']))
        <ul class="ws-people">
            @foreach ($ws['officials'] as $official)
                <li><strong>{{ $official['name'] }}</strong> <span>{{ $official['role'] }}</span></li>
            @endforeach
        </ul>
    @endif
</x-ws-modal>

<x-ws-modal id="basic" title="Datos básicos">
    <x-ws-form :action="route('workspace.categories.settings.update', $category)" section="basic" enctype="multipart/form-data">
        @include('workspace.partials.category-banner-field', [
            'src' => $category->bannerUrl(),
            'saveUrl' => route('workspace.categories.banner', $category),
            'logos' => $bannerLogos ?? [],
            'currentPath' => $category->image_path,
            'compact' => true,
            'categoryId' => $category->id,
            'identity' => $category->identityKey(),
        ])
        <label>Título <input type="text" name="name" value="{{ $category->name }}" required></label>
        <label>Descripción <textarea name="description" rows="4">{{ $ws['description'] }}</textarea></label>
        <label>Color <input type="color" name="accent_color" value="{{ $ws['accent_color'] }}"></label>
    </x-ws-form>
</x-ws-modal>
