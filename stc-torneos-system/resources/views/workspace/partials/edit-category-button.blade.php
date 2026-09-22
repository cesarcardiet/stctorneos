@php
    $editCategory = $editCategory ?? $category ?? null;
    $editTournament = $editTournament ?? $editCategory?->tournament ?? $tournament ?? null;
    $editWs = $editCategory?->workspace() ?? [];
@endphp

@if ($editCategory && $editTournament && auth()->user()?->hasPermission('tournaments.manage'))
    <button
        type="button"
        class="stc-pill-btn ghost ws-cat-edit-btn"
        data-ws-edit-cat
        data-action="{{ route('workspace.tournaments.categories.update', [$editTournament, $editCategory]) }}"
        data-name="{{ $editCategory->name }}"
        data-birth-year="{{ $editCategory->birth_year }}"
        data-branch="{{ $editCategory->branch }}"
        data-modality="{{ $editCategory->modality }}"
        data-format="{{ $editCategory->competition_format }}"
        data-groups="{{ $editCategory->groups_count }}"
        data-status="{{ $editCategory->status }}"
        data-points-win="{{ $editCategory->points_win }}"
        data-points-draw="{{ $editCategory->points_draw }}"
        data-points-loss="{{ $editCategory->points_loss }}"
        data-description="{{ $editWs['description'] ?? '' }}"
        data-image-url="{{ $editCategory->bannerUrl() }}"
        data-image-path="{{ $editCategory->image_path }}"
    >{{ $editCategoryLabel ?? 'Editar categoría' }}</button>
@endif
