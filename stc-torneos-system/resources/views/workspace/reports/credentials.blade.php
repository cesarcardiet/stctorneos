@extends('workspace.reports.layout')



@section('title', 'Carnet masivo')

@section('heading', 'Credenciales de jugadores')



@section('extra-styles')

    .cred-table { width: 100%; border-collapse: separate; border-spacing: 6px; }

    .cred-table td { width: 50%; border: 1px solid #666; border-radius: 4px; padding: 8px; vertical-align: top; height: 88px; }

    .cred-brand { font-size: 8px; letter-spacing: .08em; text-transform: uppercase; color: #555; margin-bottom: 4px; }

    .cred-name { font-size: 11px; font-weight: 700; display: block; margin-bottom: 2px; }

    .cred-line { display: block; font-size: 9px; color: #333; line-height: 1.35; }

@endsection



@section('content')

    <table class="cred-table">

        @foreach ($players->chunk(2) as $pair)

            <tr>

                @foreach ($pair as $player)

                    <td>

                        <span class="cred-brand">{{ $tournament->name }}</span>

                        <span class="cred-name">{{ $player->fullName() }}</span>

                        <span class="cred-line">{{ $category->name }}</span>

                        <span class="cred-line">{{ $player->team?->name }}</span>

                        <span class="cred-line">Nac.: {{ $player->birth_date?->format('d/m/Y') ?: '—' }}</span>

                        <span class="cred-line">DNI: {{ $player->document_number ?: '—' }}</span>

                    </td>

                @endforeach

                @if ($pair->count() === 1)

                    <td></td>

                @endif

            </tr>

        @endforeach

    </table>

@endsection


