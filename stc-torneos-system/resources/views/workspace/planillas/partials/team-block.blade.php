@php
    use App\Services\MatchPlanillaPresenter;
@endphp

<section class="planilla-team-block">
    <div class="planilla-team-grid">
        <div class="planilla-team-main">
            <h2 class="planilla-team-title">{{ $team['name'] }}</h2>

            <table class="planilla-roster">
                <thead>
                    <tr>
                        <th class="col-idx"></th>
                        <th class="col-player">Jugadores</th>
                        <th class="col-doc">Doc.</th>
                        <th class="col-no">No.</th>
                        <th class="col-card">Ama</th>
                        <th class="col-card">Roj</th>
                        <th class="col-obs">Obs.</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($team['rows'] as $index => $row)
                        <tr>
                            <td class="col-idx">{{ $index + 1 }}</td>
                            <td class="col-player">{{ $row['name'] }}</td>
                            <td class="col-doc">{{ $row['document'] }}</td>
                            <td class="col-no"></td>
                            <td class="col-card"></td>
                            <td class="col-card"></td>
                            <td class="col-obs"></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <aside class="planilla-side">
            <p class="planilla-goals-title">Goles</p>
            <div class="planilla-goals-grid">
                @for ($goal = 1; $goal <= MatchPlanillaPresenter::GOAL_SLOTS; $goal++)
                    <div class="planilla-goal-cell">
                        <div class="planilla-goal-num">{{ $goal }}</div>
                        <div class="planilla-goal-mark">{{ ! empty($team['goal_marks'][$goal] ?? null) ? 'X' : '' }}</div>
                        <div class="planilla-goal-slot">:</div>
                    </div>
                @endfor
            </div>

            <table class="planilla-subs">
                <thead>
                    <tr>
                        <th class="row-label">Sustituciones</th>
                        @for ($sub = 1; $sub <= MatchPlanillaPresenter::SUB_SLOTS; $sub++)
                            <th>{{ $sub }}</th>
                        @endfor
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <th class="row-label">Entrado</th>
                        @for ($sub = 1; $sub <= MatchPlanillaPresenter::SUB_SLOTS; $sub++)
                            <td></td>
                        @endfor
                    </tr>
                    <tr>
                        <th class="row-label">Salió</th>
                        @for ($sub = 1; $sub <= MatchPlanillaPresenter::SUB_SLOTS; $sub++)
                            <td></td>
                        @endfor
                    </tr>
                </tbody>
            </table>

            <div class="planilla-footer-lines">
                <div class="staff-row"><strong>Entrenador:</strong><span>{{ $team['coach'] }}</span></div>
                <div class="staff-row"><strong>Capitán:</strong><span>{{ $team['captain'] ?? '' }}</span></div>

                <table class="planilla-sanciones">
                    <tr>
                        <th class="san-label">Sanciones</th>
                        @for ($i = 1; $i <= 6; $i++)
                            <td class="san-blank"></td>
                        @endfor
                    </tr>
                    <tr>
                        <th class="san-label">Cuerpo tecnico</th>
                        @for ($i = 1; $i <= 6; $i++)
                            <td class="san-box"></td>
                        @endfor
                    </tr>
                    <tr>
                        <th class="san-label">Padres</th>
                        @for ($i = 1; $i <= 6; $i++)
                            <td class="san-box"></td>
                        @endfor
                    </tr>
                    <tr>
                        <th class="san-label" colspan="7">Observaciones<span class="san-dots">................................................</span></th>
                    </tr>
                </table>
            </div>
        </aside>
    </div>
</section>
