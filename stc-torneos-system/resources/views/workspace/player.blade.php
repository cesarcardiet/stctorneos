<x-layouts.workspace
    :title="$title"
    heading="Ficha del jugador"
    :subheading="$player->team?->name.' · '.$category->name"
    :category="$category"
    :tournament="$tournament"
    :active="$active"
>
    <p class="ws-back"><a href="{{ route('workspace.categories.players', [$category, 'team_id' => $player->team_id]) }}">← Jugadores</a></p>

    <section class="ws-ficha">
        <article class="ws-card">
            <p class="stc-eyebrow">Datos personales</p>
            @if ($canEdit)
                <form class="ws-form" method="post" action="{{ route('workspace.categories.players.update', [$category, $player]) }}">
                    @csrf
                    @method('PATCH')
                    <div class="ws-setup-grid">
                        <label>Nombre <input type="text" name="first_name" value="{{ $player->first_name }}" required></label>
                        <label>Apellido <input type="text" name="last_name" value="{{ $player->last_name }}" required></label>
                        <label>Documento <input type="text" name="document_number" value="{{ $player->document_number }}"></label>
                        <label>Nacimiento <input type="date" name="birth_date" value="{{ $player->birth_date?->format('Y-m-d') }}"></label>
                        <label>Camiseta <input type="number" name="jersey_number" min="1" max="99" value="{{ $player->jersey_number }}"></label>
                        <label>Estado
                            <select name="status">
                                @foreach ($statuses as $value => $label)
                                    <option value="{{ $value }}" @selected($player->status === $value)>{{ $label }}</option>
                                @endforeach
                            </select>
                        </label>
                    </div>
                    <p class="stc-eyebrow">Tutor</p>
                    <div class="ws-setup-grid">
                        <label>Nombre del tutor <input type="text" name="guardian_name" value="{{ $player->guardian?->name }}"></label>
                        <label>Teléfono <input type="text" name="guardian_phone" value="{{ $player->guardian?->phone }}"></label>
                        <label>Email <input type="email" name="guardian_email" value="{{ $player->guardian?->email }}"></label>
                        <label>Consentimiento
                            <select name="consent_status">
                                @foreach (\App\Models\Guardian::consentLabels() as $value => $label)
                                    <option value="{{ $value }}" @selected(($player->guardian?->consent_status ?? 'pending') === $value)>{{ $label }}</option>
                                @endforeach
                            </select>
                        </label>
                    </div>
                    <div class="ws-form-actions">
                        <button type="submit" class="ws-btn">Guardar ficha</button>
                    </div>
                </form>
            @else
                <p><strong>{{ $player->fullName() }}</strong></p>
                <p class="ws-muted">{{ $player->statusLabel() }} · {{ $player->document_number ?: 'sin DNI' }}</p>
            @endif
        </article>

        <article class="ws-card">
            <p class="stc-eyebrow">Documentación</p>
            <ul class="ws-people">
                @foreach ($player->documents as $document)
                    <li>
                        <div>
                            <strong>{{ $document->type }}</strong>
                            <span>{{ $document->statusLabel() }}</span>
                        </div>
                        @if ($canEdit)
                            <form method="post" action="{{ route('workspace.categories.documents.review', [$category, $document]) }}">
                                @csrf
                                @method('PATCH')
                                <input type="hidden" name="status" value="{{ $document->status === 'approved' ? 'observed' : 'approved' }}">
                                <button type="submit" class="ws-btn ghost">{{ $document->status === 'approved' ? 'Observar' : 'Aprobar' }}</button>
                            </form>
                        @endif
                    </li>
                @endforeach
            </ul>
        </article>
    </section>
</x-layouts.workspace>
