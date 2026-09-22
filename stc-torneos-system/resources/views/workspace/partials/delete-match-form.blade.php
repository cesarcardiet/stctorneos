@if (! empty($canDeleteMatches))
    <form
        method="post"
        class="{{ $class ?? '' }}"
        action="{{ route('workspace.categories.matches.destroy', [$category, $match]) }}"
        onsubmit="return confirm('¿Eliminar este partido? Se saca del fixture y de la clasificación.')"
    >
        @csrf
        @method('DELETE')
        <button type="submit" class="{{ $buttonClass ?? 'is-danger' }}">Eliminar partido</button>
    </form>
@endif
