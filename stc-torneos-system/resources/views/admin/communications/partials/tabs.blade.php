@php
    $searchQuery = array_filter(['search' => request('search')]);
@endphp
<nav class="category-tabs results-tabs">
    <a href="{{ route('admin.communications.index', $searchQuery) }}" @class(['active' => ($tab ?? '') === 'content'])>Contenido</a>
    <a href="{{ route('admin.communications.notifications', $searchQuery) }}" @class(['active' => ($tab ?? '') === 'notifications'])>Notificaciones</a>
    <a href="{{ route('admin.communications.favorites', $searchQuery) }}" @class(['active' => ($tab ?? '') === 'favorites'])>Favoritos</a>
    <a href="{{ route('admin.communications.plaques', $searchQuery) }}" @class(['active' => ($tab ?? '') === 'plaques'])>Placas</a>
</nav>
