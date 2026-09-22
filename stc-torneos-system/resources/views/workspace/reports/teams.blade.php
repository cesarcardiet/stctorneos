@extends('workspace.reports.layout')

@section('title', 'Reporte Equipos')
@section('heading', 'Listado de equipos')

@section('content')
    @foreach ($groups as $groupLabel => $rows)
        <h2>{{ $groupLabel }}</h2>
        <table>
            <thead>
                <tr>
                    <th>#</th>
                    <th>Equipo</th>
                    @foreach ($columns as $column)
                        <th>{{ $shortLabels[$column] ?? strtoupper($column) }}</th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                @forelse ($rows as $row)
                    <tr>
                        <td>{{ $row['position'] ?? '' }}</td>
                        <td>{{ $row['team']->name }}</td>
                        @foreach ($columns as $column)
                            <td>
                                @switch($column)
                                    @case('pts') {{ $row['points'] }} @break
                                    @case('j') {{ $row['played'] }} @break
                                    @case('g') {{ $row['won'] }} @break
                                    @case('e') {{ $row['drawn'] }} @break
                                    @case('p') {{ $row['lost'] }} @break
                                    @case('gf') {{ $row['gf'] }} @break
                                    @case('gc') {{ $row['ga'] }} @break
                                    @case('dif') {{ $row['gd'] }} @break
                                    @case('percent') {{ $row['percent'] }}% @break
                                    @case('pe') {{ $row['pending'] ?? 0 }} @break
                                    @default —
                                @endswitch
                            </td>
                        @endforeach
                    </tr>
                @empty
                    <tr><td colspan="{{ count($columns) + 2 }}">Sin equipos en este grupo.</td></tr>
                @endforelse
            </tbody>
        </table>
    @endforeach
@endsection
