@php
    $tab = $tab ?? 'standings';
    $query = array_filter([
        'phase' => ($selectedPhase ?? 'all') !== 'all' ? $selectedPhase : null,
        'round' => ($selectedRound ?? 'all') !== 'all' ? $selectedRound : null,
    ]);
@endphp

<nav class="category-tabs ws-competition-tabs admin-competition-tabs" aria-label="Vistas de clasificación">
    <a href="{{ route('admin.categories.competition', [$category, 'tab' => 'standings'] + $query) }}" @class(['active' => $tab === 'standings'])>Tablas</a>
    <a href="{{ route('admin.categories.competition', [$category, 'tab' => 'brackets'] + $query) }}" @class(['active' => $tab === 'brackets'])>Cruces</a>
    <a href="{{ route('admin.categories.competition', [$category, 'tab' => 'fairplay'] + $query) }}" @class(['active' => $tab === 'fairplay'])>Fair Play</a>
    <a href="{{ route('admin.categories.competition', [$category, 'tab' => 'rankings'] + $query) }}" @class(['active' => $tab === 'rankings'])>Rankings</a>
</nav>
