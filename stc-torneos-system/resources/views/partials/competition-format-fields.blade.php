@php
    $formats = $formats ?? \App\Models\Category::competitionFormats();
    $selectedFormat = old('competition_format', $competitionFormat ?? '');
    $customFormat = old('custom_competition_format', $customCompetitionFormat ?? '');
    $groupsCount = old('groups_count', $groupsCount ?? 0);
    $teamsPerGroup = old('teams_per_group', $teamsPerGroup ?? 4);
    $showTeamsPerGroup = $showTeamsPerGroup ?? true;
    $compact = $compact ?? false;
    $inline = $inline ?? false;

    if ($selectedFormat && ! in_array($selectedFormat, $formats, true)) {
        $customFormat = $customFormat ?: $selectedFormat;
        $selectedFormat = 'Formato personalizado';
    }
@endphp

@if (! $inline)
<div @class([$wrapperClass ?? 'ficha-row']) data-competition-format-root>
@endif
    <label @class(['span-2' => ! $compact])>Formato de competencia
        <select name="competition_format" data-competition-format-select required>
            <option value="" @selected($selectedFormat === '') disabled>Elegí un formato</option>
            @foreach ($formats as $format)
                <option value="{{ $format }}" @selected($selectedFormat === $format)>{{ $format }}</option>
            @endforeach
        </select>
    </label>
    <label data-competition-format-custom @hidden($selectedFormat !== 'Formato personalizado')>
        Nombre del formato
        <input
            type="text"
            name="custom_competition_format"
            value="{{ $customFormat }}"
            placeholder="Ej: Grupos de 5 + repechaje"
            maxlength="120"
            @required($selectedFormat === 'Formato personalizado')
        >
    </label>
    @if ($compact)
        <label data-competition-format-groups @hidden(! \App\Models\Category::formatNeedsGroups($selectedFormat))>
            Grupos / zonas
            <input
                type="number"
                name="groups_count"
                min="1"
                max="{{ \App\Models\Category::MAX_GROUPS }}"
                value="{{ $groupsCount > 0 ? $groupsCount : 2 }}"
                data-default-value="2"
                @required(\App\Models\Category::formatNeedsGroups($selectedFormat))
            >
        </label>
    @else
        <span data-competition-format-groups @hidden(! \App\Models\Category::formatNeedsGroups($selectedFormat)) class="span-2 ficha-row-inline">
            <label>Grupos / zonas
                <input
                    type="number"
                    name="groups_count"
                    min="1"
                    max="{{ \App\Models\Category::MAX_GROUPS }}"
                    value="{{ $groupsCount > 0 ? $groupsCount : 2 }}"
                    data-default-value="2"
                    @required(\App\Models\Category::formatNeedsGroups($selectedFormat))
                >
            </label>
            @if ($showTeamsPerGroup)
                <label>Equipos por grupo
                    <input
                        type="number"
                        name="teams_per_group"
                        min="1"
                        max="32"
                        value="{{ $teamsPerGroup > 0 ? $teamsPerGroup : 4 }}"
                        data-default-value="4"
                    >
                </label>
            @endif
        </span>
    @endif
@if (! $inline)
</div>
@endif
