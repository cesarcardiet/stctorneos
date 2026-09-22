@extends('workspace.reports.layout')

@section('title', 'Actas de juego')
@section('heading', 'Actas · elegí partido')

@section('content')
    <p class="meta">Fase: {{ $selectedPhase === 'all' ? 'Todas' : $selectedPhase }} · Fecha: {{ $selectedRound === 'all' ? 'Todas' : $selectedRound }}</p>
    <table>
        <thead>
            <tr>
                <th>Partido</th>
                <th>Fase / Fecha</th>
                <th>Cancha</th>
                <th></th>
            </tr>
        </thead>
        <tbody>
            @foreach ($matches as $match)
                <tr>
                    <td>{{ $match->homeTeam?->name }} vs {{ $match->awayTeam?->name }}</td>
                    <td>{{ $match->stage }} · {{ $match->round }}</td>
                    <td>{{ $match->field?->name ?: '—' }}</td>
                    <td>
                        <a href="{{ route('workspace.categories.matches.planilla', [$category, $match]) }}" target="_blank" rel="noopener">Ver acta</a>
                        ·
                        <a href="{{ route('workspace.categories.matches.planilla', [$category, $match, 'format' => 'pdf']) }}">Descargar PDF</a>
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>
    <p class="meta">Descargá cada acta en PDF o abrila para imprimir desde el navegador.</p>
@endsection
