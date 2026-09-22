@props(['action', 'section' => null, 'submit' => 'Guardar', 'enctype' => null])

<form class="ws-form" method="post" action="{{ $action }}" @if ($enctype) enctype="{{ $enctype }}" @endif>
    @csrf
    @if ($section)
        <input type="hidden" name="section" value="{{ $section }}">
    @endif
    {{ $slot }}
    <div class="ws-form-actions">
        <button type="button" class="ws-btn ghost" data-ws-close>Cancelar</button>
        <button type="submit" class="ws-btn">{{ $submit }}</button>
    </div>
</form>
