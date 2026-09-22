@props([
    'category',
    'tab' => 'standings',
])

<nav class="category-tabs ws-competition-tabs" aria-label="Vistas de clasificación">
    <a href="{{ route('workspace.categories.standings', $category) }}" @class(['active' => $tab === 'standings'])>Tablas</a>
    <a href="{{ route('workspace.categories.brackets', $category) }}" @class(['active' => $tab === 'brackets'])>Cruces</a>
    <a href="{{ route('workspace.categories.fairplay', $category) }}" @class(['active' => $tab === 'fairplay'])>Fair Play</a>
    <a href="{{ route('workspace.categories.rankings', $category) }}" @class(['active' => $tab === 'rankings'])>Rankings</a>
</nav>
