<x-layouts.stc
    :title="$title.' | STC Torneos'"
    active="Planillas"
    :heading="$title"
    :subheading="$subtitle"
>
    @if (session('status'))
        <div class="system-alert">{{ session('status') }}</div>
    @endif

    @if ($errors->any())
        <div class="system-alert">Revisá los datos de la planilla antes de continuar.</div>
    @endif

    @php
        $stepLabels = [
            1 => '1 Datos partido',
            2 => '2 Eventos',
            3 => '3 Incidencias',
            4 => '4 Cierre',
        ];
    @endphp

    <nav class="sheets-steps">
        @foreach ($stepLabels as $number => $label)
            @php
                $done = (int) $sheet->current_step > $number;
                $current = $number === $step;
            @endphp
            <a href="{{ $sheet->stepRoute($number) }}" @class(['is-current' => $current, 'is-done' => $done && ! $current])>
                {{ $done && ! $current ? '✓ '.preg_replace('/^\d+\s/', '', $label) : $label }}
            </a>
        @endforeach
    </nav>

    <section class="sheets-editor-layout">
        @include('admin.sheets.partials.step-'.$step)

        <aside @class(['sheets-side-card', 'is-status' => $step === 1, 'is-score' => $step === 4])>
            @if ($step === 1)
                <h3>Estado</h3>
                <p>Borrador listo para cargar eventos. Verificá equipos, cancha y responsables antes de continuar.</p>
            @elseif ($step === 2)
                <h3>Eventos cargados</h3>
                <div class="sheets-side-list">
                    <div class="table-head"><span>Min</span><span>Tipo</span><span>Jugador</span></div>
                    @forelse ($sheet->events as $event)
                        <div class="table-row">
                            <span>{{ $event->minuteLabel() }}</span>
                            <span>{{ $event->typeLabel() }}</span>
                            <span>{{ $event->actorName() }}</span>
                        </div>
                    @empty
                        <p>Todavía no hay eventos.</p>
                    @endforelse
                </div>
            @elseif ($step === 3)
                <h3>Historial de incidencias</h3>
                <div class="sheets-side-list">
                    <div class="table-head"><span>Tipo</span><span>Equipo</span><span>Estado</span></div>
                    @forelse ($sheet->incidents as $incident)
                        <div class="table-row">
                            <span>{{ $incident->type }}</span>
                            <span>{{ $incident->related_name ?: '—' }}</span>
                            <span>{{ $incident->statusLabel() }}</span>
                        </div>
                    @empty
                        <p>Sin incidencias cargadas.</p>
                    @endforelse
                </div>
            @else
                <h3>Ficha del partido</h3>
                <strong class="sheets-score">{{ $sheet->scoreLine() }}</strong>
                <p>{{ $sheet->match?->title() }}</p>
                <a class="sheets-primary-btn" href="{{ route('admin.sheets.placa', $sheet) }}" target="_blank" rel="noopener">Descargar placa</a>
                <a class="sheets-primary-btn" href="{{ route('admin.sheets.pdf', $sheet) }}" target="_blank" rel="noopener">Planilla PDF</a>
            @endif
        </aside>
    </section>
</x-layouts.stc>
