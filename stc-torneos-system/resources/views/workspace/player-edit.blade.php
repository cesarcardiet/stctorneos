@php
    $initials = strtoupper(mb_substr($player->first_name, 0, 1).mb_substr($player->last_name, 0, 1));
    $statusTone = match ($player->status) {
        'enabled', 'approved' => 'is-ok',
        'rejected', 'blocked' => 'is-bad',
        'observed' => 'is-warn',
        default => 'is-pending',
    };
@endphp

<x-layouts.workspace
    :title="$title"
    heading="Editar ficha"
    :subheading="$player->team?->name.' · '.$category->name"
    :category="$category"
    :tournament="$tournament"
    active="Jugadores"
>
    <p class="ws-back">
        <a href="{{ route('workspace.categories.players.show', [$category, $player]) }}">← Ver ficha</a>
        <a href="{{ route('workspace.categories.players', [$category, 'team_id' => $player->team_id]) }}">Jugadores</a>
        @if ($player->team)
            <a href="{{ route('workspace.categories.teams.show', [$category, $player->team]) }}">Equipo</a>
        @endif
    </p>

    @if (session('status'))
        <div class="ws-alert">{{ session('status') }}</div>
    @endif

    @if ($rosterLockReason ?? null)
        <div class="ws-alert">{{ $rosterLockReason }}</div>
    @endif

    <section class="ws-ficha-edit">
        <header class="ws-card ws-ficha-hero">
            <div class="ws-ficha-hero-photo">
                @if ($player->hasStoredPhoto())
                    <img src="{{ $player->photoUrl() }}" alt="{{ $player->fullName() }}">
                    <a href="{{ $player->photoUrl() }}" target="_blank" rel="noopener">Ver foto</a>
                @else
                    <div class="ws-ficha-avatar-fallback" aria-hidden="true">{{ $initials ?: '?' }}</div>
                    <span class="ws-muted ws-ficha-photo-hint">Sin foto cargada</span>
                @endif
                <form class="ws-photo-change" method="post" action="{{ route('workspace.categories.players.documents', [$category, $player]) }}" enctype="multipart/form-data" data-ws-shield-crop data-ws-crop-kind="player" data-ws-shield-save="{{ route('workspace.categories.players.documents', [$category, $player]) }}">
                    @csrf
                    <input type="hidden" name="type" value="Foto del jugador">
                    <img data-ws-shield-preview src="{{ $player->photoUrl() }}" alt="" hidden>
                    <label>
                        Cambiar foto
                        <input type="file" name="file" accept="image/png,image/jpeg,image/webp">
                    </label>
                    <small>Se guarda al confirmar el recorte, sin recorrer los pasos.</small>
                </form>
            </div>
            <div class="ws-ficha-hero-body">
                <p class="stc-eyebrow">Editando ficha</p>
                <h2 class="ws-ficha-hero-name">{{ $player->fullName() }}</h2>
                <p class="ws-ficha-hero-meta">
                    {{ $player->team?->name ?: 'Sin equipo' }}
                    · {{ $category->name }}
                    @if ($player->jersey_number)
                        · Camiseta {{ $player->jersey_number }}
                    @endif
                </p>
                <div class="ws-ficha-hero-badges">
                    <span class="ws-status-pill {{ $statusTone }}">{{ $player->statusLabel() }}</span>
                    <span class="ws-status-pill is-muted">{{ $player->documentationSummary() }}</span>
                    <span class="ws-status-pill is-muted">{{ $player->guardian?->consentLabel() ?? 'Sin tutor' }}</span>
                </div>
            </div>
        </header>

        <form
            class="ws-form ws-ficha-edit-form ws-ficha-steps"
            method="post"
            action="{{ route('workspace.categories.players.update', [$category, $player]) }}"
            data-ficha-steps
        >
            @csrf
            @method('PATCH')

            <ol class="ws-ficha-stepper" aria-label="Pasos de la ficha">
                <li class="is-current" data-ficha-dot><span>1</span> Jugador</li>
                <li data-ficha-dot><span>2</span> Plantel</li>
                <li data-ficha-dot><span>3</span> Tutor</li>
            </ol>

            <article class="ws-card ws-ficha-panel" data-ficha-step>
                <header class="ws-ficha-panel-head">
                    <h3>Datos del jugador</h3>
                    <p>Identidad. El equipo y la categoría quedan arriba.</p>
                </header>
                <div class="ws-setup-grid ws-form-grid">
                    <x-ws-form-field label="Nombre">
                        <input type="text" name="first_name" value="{{ $player->first_name }}" placeholder="Ej: Lautaro" required>
                    </x-ws-form-field>
                    <x-ws-form-field label="Apellido">
                        <input type="text" name="last_name" value="{{ $player->last_name }}" placeholder="Ej: Cabrera" required>
                    </x-ws-form-field>
                    <x-ws-form-field label="Documento">
                        <input type="text" name="document_number" value="{{ $player->document_number }}" placeholder="DNI sin puntos">
                    </x-ws-form-field>
                    <x-ws-form-field label="Fecha de nacimiento">
                        <input type="date" name="birth_date" data-birth-date value="{{ $player->birth_date?->format('Y-m-d') }}" min="1990-01-01" max="{{ now()->format('Y-m-d') }}">
                    </x-ws-form-field>
                    <x-ws-form-field label="Nacionalidad">
                        <x-country-select name="nationality" :value="$player->nationality ?: 'Argentina'" />
                    </x-ws-form-field>
                    <x-ws-form-field label="Domicilio">
                        <input type="text" name="address" value="{{ $player->address }}" placeholder="Calle, número, ciudad">
                    </x-ws-form-field>
                </div>
            </article>

            <article class="ws-card ws-ficha-panel" data-ficha-step hidden>
                <header class="ws-ficha-panel-head">
                    <h3>Datos de juego</h3>
                    <p>{{ $player->team?->name ?: 'Sin equipo' }} · {{ $category->name }}</p>
                </header>
                <div class="ws-setup-grid ws-form-grid">
                    <x-ws-form-field label="Posición">
                        <select name="position">
                            <option value="">Elegí posición</option>
                            @foreach (\App\Models\Player::positions() as $position)
                                <option value="{{ $position }}" @selected($player->position === $position)>{{ $position }}</option>
                            @endforeach
                        </select>
                    </x-ws-form-field>
                    <x-ws-form-field label="N° de camiseta">
                        <input type="number" name="jersey_number" min="1" max="99" value="{{ $player->jersey_number }}" placeholder="Ej: 10">
                    </x-ws-form-field>
                    <x-ws-form-field label="Pierna hábil">
                        <select name="preferred_foot">
                            <option value="">Elegí pierna</option>
                            @foreach (\App\Models\Player::preferredFeet() as $foot)
                                <option value="{{ $foot }}" @selected($player->preferred_foot === $foot)>{{ $foot }}</option>
                        @endforeach
                        </select>
                    </x-ws-form-field>
                    <x-ws-form-field label="Talle">
                        <select name="kit_size">
                            <option value="">Sin talle</option>
                            @foreach (\App\Models\Player::kitSizeGroups() as $group => $sizes)
                                <optgroup label="{{ $group }}">
                                    @foreach ($sizes as $size)
                                        <option value="{{ $size }}" @selected((string) $player->kit_size === (string) $size)>{{ $size }}</option>
                                    @endforeach
                                </optgroup>
                            @endforeach
                        </select>
                    </x-ws-form-field>
                    <x-ws-form-field label="Altura">
                        <input type="text" name="height" value="{{ $player->height }}" placeholder="Ej: 1.72 m">
                    </x-ws-form-field>
                    <x-ws-form-field label="Peso">
                        <input type="text" name="weight" value="{{ $player->weight }}" placeholder="Ej: 68 kg">
                    </x-ws-form-field>
                </div>
            </article>

            <article class="ws-card ws-ficha-panel" data-ficha-step hidden>
                <header class="ws-ficha-panel-head">
                    <h3>Tutor y cierre</h3>
                    <p>El adulto responsable. La ficha médica la completa el padre en su enlace.</p>
                </header>
                <div class="ws-setup-grid ws-form-grid">
                    <x-ws-form-field label="Nombre del tutor">
                        <input type="text" name="guardian_name" value="{{ $player->guardian?->name }}" placeholder="Nombre completo">
                    </x-ws-form-field>
                    <x-ws-form-field label="D.N.I. del tutor">
                        <input type="text" name="guardian_document_number" value="{{ $player->guardian?->document_number }}" placeholder="30123456">
                    </x-ws-form-field>
                    <x-ws-form-field label="Vínculo">
                        <select name="guardian_relationship">
                            <option value="">Elegí vínculo</option>
                            @foreach (\App\Models\Guardian::relationshipOptions() as $relationship)
                                <option value="{{ $relationship }}" @selected(($player->guardian?->relationship ?? '') === $relationship)>{{ $relationship }}</option>
                            @endforeach
                        </select>
                    </x-ws-form-field>
                    <x-ws-form-field label="Teléfono">
                        <input type="text" name="guardian_phone" value="{{ $player->guardian?->phone }}" placeholder="+54 9 11 1234-5678">
                    </x-ws-form-field>
                    <x-ws-form-field label="Email">
                        <input type="email" name="guardian_email" value="{{ $player->guardian?->email }}" placeholder="tutor@email.com">
                    </x-ws-form-field>
                    <x-ws-form-field label="Consentimiento">
                        <select name="consent_status">
                            @foreach (\App\Models\Guardian::consentLabels() as $value => $label)
                                <option value="{{ $value }}" @selected(($player->guardian?->consent_status ?? 'pending') === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </x-ws-form-field>
                    <x-ws-form-field label="Estado de la ficha">
                        <select name="status" required>
                            @foreach ($statuses as $value => $label)
                                <option value="{{ $value }}" @selected($player->status === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </x-ws-form-field>
                    <x-ws-form-field label="Motivo de observación">
                        <input type="text" name="observation_reason" value="{{ $player->observation_reason }}" placeholder="Solo si está observada">
                    </x-ws-form-field>
                </div>

                <div class="ws-ficha-docs-visual">
                <p class="stc-eyebrow">Documentos</p>
                <ul>
                    @foreach (\App\Models\Player::uploadDocumentTypes() as $type)
                        @php $document = $player->documentByType($type); @endphp
                        <li>
                            <span>{{ $type }}</span>
                            <b @class([
                                'is-ok' => $document?->status === 'approved',
                                'is-warn' => in_array($document?->status, ['observed', 'rejected'], true),
                            ])>{{ $document?->statusLabel() ?? 'Sin cargar' }}</b>
                        </li>
                    @endforeach
                </ul>
                </div>

                <x-ws-guardian-invite :player="$player" :category="$category" :can-edit="true" />
            </article>

            <div class="ws-ficha-step-actions">
                <button type="button" class="ws-btn ghost" data-ficha-prev hidden>Anterior</button>
                <div class="ws-ficha-step-nav">
                    <button type="button" class="ws-btn" data-ficha-next>Siguiente</button>
                    <button type="submit" class="ws-btn" data-ficha-finish hidden>Guardar ficha</button>
                </div>
            </div>
        </form>

        <article class="ws-card ws-ficha-danger">
            <header class="ws-ficha-panel-head">
                <h3>Zona de riesgo</h3>
                <p>Sacar al jugador del plantel de esta categoría.</p>
            </header>
            <form method="post" action="{{ route('workspace.categories.players.destroy', [$category, $player]) }}" data-confirm="¿Sacar a {{ $player->fullName() }} de este plantel?">
                @csrf
                @method('DELETE')
                <button type="submit" class="ws-btn danger">Eliminar jugador</button>
            </form>
        </article>
    </section>

</x-layouts.workspace>
