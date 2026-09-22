<x-layouts.stc
    :title="$player->fullName().' | STC Torneos'"
    active="Jugadores"
    heading="Ficha Individual del Jugador"
    :subheading="$player->fullName().' · '.($player->team?->name ?? 'Sin equipo').' · Categoría '.($player->team?->category?->birth_year ?? '—').' · ficha pública y administrativa.'"
>
    @if (session('status'))
        <div class="system-alert">{{ session('status') }}</div>
    @endif

    <section class="player-ficha-top">
        <article class="player-summary-card">
            <img src="{{ $player->photoUrl() }}" alt="{{ $player->fullName() }}">
            <div>
                <h2>{{ mb_strtoupper($player->fullName()) }}</h2>
                <p>
                    @if ($player->team)
                        <a href="{{ route('admin.teams.show', $player->team) }}">{{ $player->team->name }}</a>
                    @else
                        Sin equipo
                    @endif
                    · {{ $player->position ?: 'Sin posición' }} · N° {{ $player->jersey_number ?: '—' }}
                </p>
                <a class="player-card-button" href="{{ route('admin.players.show', [$player, 'tab' => 'card']) }}">Ver player card</a>
                <div class="player-mini-stats">
                    <article>
                        <strong>{{ $matchCount }}</strong>
                        <span>Partidos</span>
                    </article>
                    <article class="tone-green">
                        <strong>{{ $goalCount }}</strong>
                        <span>Goles</span>
                    </article>
                </div>
            </div>
        </article>

        <article class="player-docs-card">
            @php
                $dniApproved = $player->hasApprovedDocument('DNI frente') && $player->hasApprovedDocument('DNI dorso');
                $dniLabel = $dniApproved ? 'Aprobado' : $player->documentLabel('DNI frente');
                $medicalApproved = $player->hasApprovedDocument('Apto médico');
                $medicalLabel = $medicalApproved ? 'Aprobada' : $player->documentLabel('Apto médico');
                $authApproved = $player->hasApprovedDocument('Autorización');
                $authLabel = $authApproved ? 'Firmada' : $player->documentLabel('Autorización');
                $credentialOk = $player->status === 'enabled';
                $credentialLabel = $credentialOk ? 'Habilitada' : 'Pendiente';
                $summaryDocs = [
                    ['DNI', $dniLabel, $dniApproved ? 'approved' : $player->documentStatus('DNI frente'), null],
                    ['Ficha médica', $medicalLabel, $medicalApproved ? 'approved' : $player->documentStatus('Apto médico'), null],
                    ['Autorización', $authLabel, $authApproved ? 'approved' : $player->documentStatus('Autorización'), null],
                    ['Credencial', $credentialLabel, $credentialOk ? 'approved' : 'pending', route('admin.players.credential', $player)],
                ];
            @endphp
            <ul>
                @foreach ($summaryDocs as [$label, $value, $status, $href])
                    <li class="status-{{ $status }}">
                        <span>{{ $label }}</span>
                        @if ($href)
                            <a href="{{ $href }}" target="_blank" rel="noopener">{{ $value }}</a>
                        @else
                            <b>{{ $value }}</b>
                        @endif
                    </li>
                @endforeach
                @foreach (\App\Models\Player::documentTypes() as $type)
                    @continue(in_array($type, ['DNI frente', 'DNI dorso', 'Apto médico', 'Autorización'], true))
                    <li class="status-{{ $player->documentStatus($type) }}"><span>{{ $type }}</span><b>{{ $player->documentLabel($type) }}</b></li>
                @endforeach
            </ul>
            <a class="stc-btn-new" href="{{ route('admin.players.show', [$player, 'tab' => 'aprobacion']) }}">Aprobar y habilitar</a>
        </article>
    </section>

    <div class="player-tabs-bar">
        <nav class="player-tabs">
            @foreach (['aprobacion' => 'Aprobación', 'resumen' => 'Resumen', 'personal' => 'Personal', 'deportiva' => 'Deportiva', 'responsables' => 'Familiar', 'medica' => 'Médica', 'autorizaciones' => 'Autorizaciones', 'historial' => 'Historial'] as $key => $label)
                <a href="{{ route('admin.players.show', [$player, 'tab' => $key]) }}" @class(['active' => $tab === $key || ($key === 'responsables' && $tab === 'familiar') || ($key === 'aprobacion' && $tab === 'documentacion')])>{{ $label }}</a>
            @endforeach
        </nav>
        <a class="player-review-link" href="{{ route('admin.players.edit', $player) }}">→ Abrir revisión completa de ficha</a>
    </div>

    @if ($tab === 'card')
        <section class="player-card-stage">
            <article>
                <img src="{{ $player->photoUrl() }}" alt="{{ $player->fullName() }}">
                <h2>{{ mb_strtoupper($player->fullName()) }}</h2>
                <p>
                    @if ($player->team)
                        <a href="{{ route('admin.teams.show', $player->team) }}">{{ $player->team->name }}</a>
                    @endif
                    · {{ $player->position }} · N° {{ $player->jersey_number }}
                </p>
                <p>{{ $matchCount }} partidos · {{ $goalCount }} goles</p>
                <div class="form-actions" style="margin-top: 1rem; display:flex; gap:.8rem; justify-content:center;">
                    <a class="player-card-button" href="{{ route('admin.players.credential', $player) }}" target="_blank" rel="noopener">Credencial con QR</a>
                    <button type="button" onclick="window.print()">Imprimir player card</button>
                </div>
            </article>
        </section>
    @elseif ($tab === 'personal')
        <section class="delegation-form-card">
            <h3>Datos personales</h3>
            <ul class="player-data-list">
                <li><span>DNI</span><strong>{{ $player->formattedDocument() }}</strong></li>
                <li><span>Nacimiento</span><strong>{{ $player->birth_date?->format('d/m/Y') ?: 'Sin cargar' }}</strong></li>
                <li><span>Edad</span><strong>{{ $player->age() ?? '—' }}</strong></li>
                <li><span>Nacionalidad</span><strong>{{ $player->nationality }}</strong></li>
                <li><span>Domicilio</span><strong>{{ $player->address ?: 'Sin cargar' }}</strong></li>
            </ul>
        </section>
    @elseif ($tab === 'deportiva')
        <section class="delegation-form-card">
            <h3>Información deportiva</h3>
            <ul class="player-data-list">
                <li><span>Equipo</span><strong>
                    @if ($player->team)
                        <a href="{{ route('admin.teams.show', $player->team) }}">{{ $player->team->name }}</a>
                    @else
                        Sin equipo
                    @endif
                </strong></li>
                <li><span>Delegación</span><strong>
                    @if ($player->team?->delegation)
                        <a href="{{ route('admin.delegations.show', $player->team->delegation) }}">{{ $player->team->delegation->name }}</a>
                    @else
                        {{ $player->team?->delegation_name ?? '—' }}
                    @endif
                </strong></li>
                <li><span>Categoría</span><strong>
                    @if ($player->team?->category)
                        <a href="{{ route('admin.categories.show', $player->team->category) }}">{{ $player->team->category->name }}</a>
                    @else
                        —
                    @endif
                </strong></li>
                <li><span>Posición</span><strong>{{ $player->position ?: '—' }}</strong></li>
                <li><span>Camiseta</span><strong>{{ $player->jersey_number ?: '—' }}</strong></li>
                <li><span>Talle</span><strong>{{ $player->kitSizeLabel() }}</strong></li>
                <li><span>Pierna hábil</span><strong>{{ $player->preferred_foot ?: '—' }}</strong></li>
                <li><span>Altura</span><strong>{{ $player->height ?: '—' }}</strong></li>
                <li><span>Peso</span><strong>{{ $player->weight ?: '—' }}</strong></li>
                <li><span>Partidos</span><strong>{{ $matchCount }}</strong></li>
                <li><span>Goles</span><strong>{{ $goalCount }}</strong></li>
            </ul>
        </section>
    @elseif (in_array($tab, ['familiar', 'responsables'], true))
        <section class="delegation-form-card">
            <h3>Tutor / padre responsable</h3>
            @if ($player->guardian)
                <ul class="player-data-list">
                    <li><span>Nombre</span><strong>{{ $player->guardian->name }}</strong></li>
                    <li><span>D.N.I.</span><strong>{{ $player->guardian->document_number ?: '—' }}</strong></li>
                    <li><span>Vínculo</span><strong>{{ $player->guardian->relationship }}</strong></li>
                    <li><span>Email</span><strong>{{ $player->guardian->email ?: '—' }}</strong></li>
                    <li>
                        <span>Teléfono</span>
                        <strong>
                            {{ $player->guardian->phone ?: '—' }}
                            <x-whatsapp-link :url="$player->whatsappShareUrl()" />
                        </strong>
                    </li>
                    <li><span>Contacto alternativo</span><strong>{{ $player->guardian->alternate_contact ?: '—' }}</strong></li>
                    <li><span>Consentimiento</span><strong>{{ $player->guardian->consentLabel() }}</strong></li>
                </ul>
            @else
                <p>Todavía no hay tutor cargado en esta ficha.</p>
            @endif
        </section>
        @php $invitation = $player->latestGuardianInvitation(); @endphp
        <section class="delegation-form-card" style="margin-top: 1.2rem;">
            <h3>Confirmación legal del tutor</h3>
            @if ($invitation)
                <ul class="player-data-list">
                    <li><span>Estado</span><strong>{{ $invitation->familyStatusLabel() }}</strong></li>
                    <li><span>Email</span><strong>{{ $invitation->email }}</strong></li>
                    <li><span>Vence</span><strong>{{ $invitation->expires_at?->format('d/m/Y H:i') ?: '—' }}</strong></li>
                    <li><span>Enlace</span><strong><a href="{{ $invitation->publicUrl() }}" target="_blank" rel="noopener">Enlace de confirmación</a></strong></li>
                </ul>
            @else
                <p>Todavía no hay un enlace generado para el tutor.</p>
            @endif
            <form method="post" action="{{ route('admin.players.invite', $player) }}" style="margin-top: 1rem;">
                @csrf
                <div class="ficha-row">
                    <label>Email del tutor <input type="email" name="email" value="{{ $player->guardian?->email }}" placeholder="tutor@correo.com"></label>
                </div>
                <button class="ficha-save" type="submit">Generar enlace</button>
            </form>
            @if ($invitation && $invitation->isUsable())
                <div class="form-actions" style="margin-top: .8rem; display:flex; gap:.8rem;">
                    <form method="post" action="{{ route('admin.players.invite.regenerate', $player) }}">
                        @csrf
                        <button type="submit">Regenerar</button>
                    </form>
                    <form method="post" action="{{ route('admin.players.invite.invalidate', $player) }}" data-confirm="¿Invalidar el enlace actual?">
                        @csrf
                        @method('PATCH')
                        <button type="submit">Invalidar</button>
                    </form>
                </div>
            @endif
        </section>
    @elseif ($tab === 'medica')
        <section class="delegation-form-card">
            <h3>Ficha médica</h3>
            <ul class="player-data-list">
                <li><span>Grupo sanguíneo</span><strong>{{ $player->blood_type ?: '—' }}</strong></li>
                <li><span>Cobertura</span><strong>{{ $player->medical_coverage ?: '—' }}</strong></li>
                <li><span>Alergias</span><strong>{{ $player->allergies ?: 'Ninguna' }}</strong></li>
                <li><span>Medicación</span><strong>{{ $player->medication ?: 'Ninguna' }}</strong></li>
                <li><span>Enfermedades</span><strong>{{ $player->illnesses ?: 'Ninguna' }}</strong></li>
                <li><span>Restricciones alimentarias</span><strong>{{ $player->restrictions ?: 'Ninguna' }}</strong></li>
                <li><span>Calendario nacional de vacunación</span><strong>{{ $player->vaccinationCalendarLabel() }}</strong></li>
                <li><span>Tratamiento en curso</span><strong>{{ $player->ongoingTreatmentLabel() }}</strong></li>
                @if ($player->ongoing_treatment && filled($player->ongoing_treatment_notes))
                    <li><span>Detalle del tratamiento</span><strong>{{ $player->ongoing_treatment_notes }}</strong></li>
                @endif
                <li><span>Contacto emergencia</span><strong>{{ $player->emergency_contact ?: '—' }}</strong></li>
            </ul>
            <p>{{ $player->medical_notes ?: 'Sin observaciones médicas cargadas.' }}</p>
        </section>
    @elseif (in_array($tab, ['aprobacion', 'documentacion'], true))
        @include('admin.players.partials.document-review-panel', ['player' => $player, 'canApprove' => $canApprove ?? false])
        <div class="form-actions" style="margin-top: 1.2rem;">
            <a href="{{ route('admin.documents.requirements') }}">Requisitos documentales del torneo</a>
            <a href="{{ route('admin.players.edit', $player) }}">Editar ficha</a>
        </div>
    @elseif ($tab === 'autorizaciones')
        <section class="delegation-form-card">
            <h3>Autorizaciones legales del tutor</h3>
            @php $certificate = $player->guardianCertificate(); @endphp
            @if ($certificate?->fileUrl())
                <p class="login-copy">
                    <a href="{{ $certificate->fileUrl() }}" target="_blank" rel="noopener">Ver constancia de autorización del tutor</a>
                    · {{ $certificate->notes }}
                </p>
            @else
                <p>Todavía no hay constancia generada. El tutor debe completar el enlace de autorización.</p>
            @endif
            <ul class="player-data-list">
                <li><span>Autorización</span><strong>{{ $player->documentLabel('Autorización') }}</strong></li>
                <li><span>Uso de imagen</span><strong>{{ $player->documentLabel('Uso de imagen') }}</strong></li>
                <li><span>Apto médico</span><strong>{{ $player->documentLabel('Apto médico') }}</strong></li>
                <li><span>Resumen</span><strong>{{ $player->authorizationSummary() }}</strong></li>
            </ul>
        </section>
    @elseif ($tab === 'historial')
        <section class="delegation-list-card player-history-panel">
            <header><h3>Historial deportivo</h3></header>
            <div class="delegation-figma-table history-figma-table">
                <div class="table-head"><span>Fecha</span><span>Rival</span><span>Resultado</span><span>Eventos</span></div>
                @forelse ($history as $row)
                    <div class="table-row">
                        <span>{{ $row['date'] }}</span>
                        <span>{{ $row['rival'] }}</span>
                        <span>{{ $row['score'] }}</span>
                        <span>{{ $row['events'] }}</span>
                    </div>
                @empty
                    <div class="category-empty-state"><strong>Todavía no hay partidos cargados para este plantel.</strong></div>
                @endforelse
            </div>
        </section>
    @else
        <section class="delegation-form-card">
            <h3>Resumen de ficha</h3>
            <ul class="player-data-list">
                <li><span>Capa 1 · Delegado</span><strong>{{ $player->layerOneLabel() }}</strong></li>
                <li><span>Capa 2 · Tutor</span><strong>{{ $player->layerTwoLabel() }}</strong></li>
                <li><span>Estado ficha</span><strong>{{ $player->fileStatusLabel() }}</strong></li>
                <li><span>Revisión</span><strong>{{ $player->reviewStatusLabel() }}</strong></li>
                <li><span>Documentación</span><strong>{{ $player->documentationSummary() }}</strong></li>
                <li><span>Autorizaciones</span><strong>{{ $player->authorizationSummary() }}</strong></li>
                <li><span>Habilitación</span><strong>{{ $player->eligibilityLabel() }}</strong></li>
                <li><span>Observación</span><strong>{{ $player->observation_reason ?: 'Sin observaciones' }}</strong></li>
            </ul>
            @if ($canApprove ?? false)
                <div class="form-actions" style="margin-top: 1rem;">
                    <a class="stc-btn-new" href="{{ route('admin.players.show', [$player, 'tab' => 'aprobacion']) }}">
                        Ir a aprobación · {{ $player->documentationSummary() }} · {{ $player->eligibilityLabel() }}
                    </a>
                </div>
            @endif
            @include('admin.players.partials.status-actions', ['player' => $player])
        </section>
        <section class="delegation-list-card player-history-panel" style="margin-top: 1.15rem;">
            <header><h3>Historial deportivo</h3></header>
            <div class="delegation-figma-table history-figma-table">
                <div class="table-head"><span>Fecha</span><span>Rival</span><span>Resultado</span><span>Eventos</span></div>
                @forelse ($history as $row)
                    <div class="table-row">
                        <span>{{ $row['date'] }}</span>
                        <span>{{ $row['rival'] }}</span>
                        <span>{{ $row['score'] }}</span>
                        <span>{{ $row['events'] }}</span>
                    </div>
                @empty
                    <div class="category-empty-state"><strong>Todavía no hay partidos cargados para este plantel.</strong></div>
                @endforelse
            </div>
        </section>
    @endif
</x-layouts.stc>
