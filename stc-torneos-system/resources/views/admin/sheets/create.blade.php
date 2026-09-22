<x-layouts.stc
    title="Nueva planilla | STC Torneos"
    active="Planillas"
    heading="Planillas"
    subheading="Elegí el partido para abrir la carga oficial."
>
    <a class="back-link" href="{{ route('admin.sheets.index') }}">← Volver al listado</a>

    @if ($errors->any())
        <div class="system-alert">Seleccioná un partido del fixture para crear la planilla.</div>
    @endif

    <form class="ficha-review-panel admin-figma-form" method="post" action="{{ route('admin.sheets.store') }}">
        @csrf
        <h3>Partido</h3>
        <div class="ficha-row">
            <label class="span-4">Partido
                <select name="match_id" required>
                    <option value="">Elegí un partido sin planilla</option>
                    @foreach ($matches as $match)
                        <option value="{{ $match->id }}">{{ $match->title() }} · {{ $match->scheduled_at?->format('d/m H:i') }} · {{ $match->field?->name }}</option>
                    @endforeach
                </select>
            </label>
        </div>
        <p class="ficha-flow">La nueva planilla arranca en borrador. Después cargás datos, eventos, incidencias y el cierre.</p>
        <div class="admin-figma-form-actions">
            <a class="admin-figma-cancel" href="{{ route('admin.sheets.index') }}">CANCELAR</a>
            <button class="ficha-save" type="submit">Crear planilla</button>
        </div>
    </form>
</x-layouts.stc>
