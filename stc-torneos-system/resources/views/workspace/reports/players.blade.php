@extends('workspace.reports.layout')

@section('title', 'Reporte Jugadores')
@section('heading', 'Listado de jugadores')

@section('content')
    @foreach ($teams as $team)
        <h2>{{ $team->name }}</h2>
        <table>
            <thead>
                <tr>
                    <th>Nombre del jugador</th>
                    <th>Faltas</th>
                    <th>Evaluación</th>
                    <th>Asistencias</th>
                    <th>Fecha de nacimiento</th>
                    <th>Goles</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($team->players as $player)
                    @php $row = $stats->get($player->id, ['cards' => 0, 'assists' => 0, 'goals' => 0]); @endphp
                    <tr>
                        <td>{{ $player->fullName() }}</td>
                        <td>{{ $row['cards'] ?: '—' }}</td>
                        <td>—</td>
                        <td>{{ $row['assists'] ?: '—' }}</td>
                        <td>{{ $player->birth_date?->format('d/m/Y') ?: '—' }}</td>
                        <td>{{ $row['goals'] ?: '—' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="6">Sin jugadores cargados.</td></tr>
                @endforelse
            </tbody>
        </table>
    @endforeach
@endsection
