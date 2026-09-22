@php
    $authContext = \App\Support\GuardianAuthorizationTexts::context($player, $guardian);

    $fichaFieldSteps = [
        'last_name' => 1, 'first_name' => 1, 'document_number' => 1,
        'guardian_first_name' => 1, 'guardian_last_name' => 1, 'guardian_name' => 1,
        'guardian_document_number' => 1, 'relationship' => 1, 'email' => 1, 'phone' => 1,
        'guardian_alternate_name' => 1, 'guardian_alternate_phone' => 1, 'guardian_alternate_contact' => 1,
        'birth_date' => 2, 'birth_date_display' => 2, 'nationality' => 2, 'address' => 2,
        'kit_size' => 3, 'jersey_number' => 3, 'position' => 3, 'preferred_foot' => 3, 'height' => 3, 'weight' => 3,
        'blood_type' => 4, 'medical_coverage' => 4, 'allergies' => 4, 'medication' => 4, 'illnesses' => 4,
        'restrictions' => 4, 'vaccination_calendar_complete' => 4, 'ongoing_treatment' => 4,
        'ongoing_treatment_notes' => 4, 'emergency_contact' => 4, 'medical_notes' => 4,
        'document_files' => 5, 'photo_data' => 5,
        'auth' => 6, 'consent' => 9, 'player_email' => 9, 'complete' => 9,
    ];

    $fichaErrorSteps = [];
    if (isset($errors) && $errors->any()) {
        foreach ($errors->keys() as $key) {
            $base = explode('.', (string) $key)[0];
            if (str_starts_with((string) $key, 'document_files')) {
                $fichaErrorSteps[] = 5;
                continue;
            }
            if ($base === 'auth') {
                $fichaErrorSteps[] = 6;
                continue;
            }
            if (isset($fichaFieldSteps[$base])) {
                $fichaErrorSteps[] = $fichaFieldSteps[$base];
            }
        }
        $fichaErrorSteps = array_values(array_unique($fichaErrorSteps));
        sort($fichaErrorSteps);
    }

    $fichaInitialStep = $fichaErrorSteps[0] ?? (int) ($fichaInitialStep ?? 1);
@endphp

@if ($locked)
    <div class="ficha-wizard ficha-wizard-locked">
        @include('ficha.partials.locked-notice')
        @include('ficha.partials.completed-summary')
    </div>
@else
    <div
        class="ficha-wizard @if(!empty($panelClass)) {{ $panelClass }} @endif"
        data-ficha-wizard
        data-locked="0"
        data-total-steps="9"
        data-initial-step="{{ $fichaInitialStep }}"
        data-error-steps="{{ implode(',', $fichaErrorSteps) }}"
        data-check-email-url="{{ $fichaCheckEmailUrl ?? '' }}"
        data-auth-context='@json($authContext)'
        data-player-name="{{ $authContext['player_name'] }}"
        data-player-document="{{ $authContext['player_document'] }}"
        data-team-name="{{ $authContext['team_name'] }}"
        data-tournament-name="{{ $authContext['tournament_name'] }}"
    >
        @include('ficha.partials.wizard-panels')
    </div>
@endif

@unless ($locked)
    @isset($loadFichaAssets)
        @push('head')
            @vite(['resources/js/ficha.js'])
        @endpush
        @push('scripts')
            <script>
                window.setTimeout(function () {
                    if (document.querySelector('[data-ficha-wizard][data-locked="0"]') && !document.querySelector('[data-ficha-wizard][data-current-step]')) {
                        window.alert('No se pudo cargar el asistente. Recargá la página con Ctrl+F5.');
                    }
                }, 1500);
            </script>
        @endpush
    @else
        <script>
            window.setTimeout(function () {
                if (document.querySelector('[data-ficha-wizard][data-locked="0"]') && !document.querySelector('[data-ficha-wizard][data-current-step]')) {
                    window.alert('No se pudo cargar el asistente. Recargá la página con Ctrl+F5.');
                }
            }, 1500);
        </script>
    @endisset
@endunless
