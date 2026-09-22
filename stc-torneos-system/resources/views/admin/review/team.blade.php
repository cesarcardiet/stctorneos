<x-layouts.stc
    :title="'Plantel · '.$team->name.' | STC Torneos'"
    active="Revisión"
    heading="Revisión"
    :subheading="$category->name.' · '.$team->name"
>
    <a class="back-link" href="{{ route('admin.review.category', $category) }}">← Equipos de {{ $category->name }}</a>
    @if (session('status'))
        <div class="system-alert">{{ session('status') }}</div>
    @endif
    <p class="ficha-flow">Plantel de {{ $team->name }}. Revisá si el tutor/padre ya aprobó y, si corresponde, aprobá al jugador.</p>

    <section class="stc-card table-card team-list-panel review-list-panel admin-figma-panel">
        <header class="admin-list-heading">
            <h3>Jugadores</h3>
        </header>
        <div class="stc-table team-roster-table admin-figma-table">
            <div class="table-head">
                <span>Jugador</span>
                <span>Ficha</span>
                <span>Tutor / Padre</span>
                <span>Habilitación</span>
                <span></span>
            </div>
            @forelse ($team->players as $player)
                <div class="table-row">
                    <span>
                        <x-entity-cell
                            :href="route('admin.players.show', $player)"
                            :src="$player->listPhotoUrl()"
                            :alt="$player->fullName()"
                            shape="round"
                        >
                            {{ $player->fullName() }}
                            <x-slot:subtitle>{{ $player->formattedDocument() }}</x-slot:subtitle>
                        </x-entity-cell>
                    </span>
                    <span>{{ $player->fileStatusLabel() }}</span>
                    <span>{{ $player->guardianConsentLabel() }}</span>
                    <span>{{ $player->eligibilityLabel() }}</span>
                    <span class="category-actions">
                        <button
                            type="button"
                            class="link-button"
                            data-player-review
                            data-name="{{ $player->fullName() }}"
                            data-status="{{ $player->status }}"
                            data-status-label="{{ $player->statusLabel() }}"
                            data-guardian="{{ $player->guardian?->name ?? 'Sin tutor cargado' }}"
                            data-relationship="{{ $player->guardian?->relationship ?? '—' }}"
                            data-email="{{ $player->guardian?->email ?? '—' }}"
                            data-phone="{{ $player->guardian?->phone ?? '—' }}"
                            data-consent="{{ $player->guardian?->consent_status ?? 'pending' }}"
                            data-consent-label="{{ $player->guardianConsentLabel() }}"
                            data-action="{{ route('admin.players.review', $player) }}"
                        >Revisar</button>
                    </span>
                </div>
            @empty
                <div class="table-row">
                    <span><strong>Este equipo no tiene jugadores.</strong></span>
                    <span>-</span>
                    <span>-</span>
                    <span>-</span>
                    <span></span>
                </div>
            @endforelse
        </div>
    </section>

    <div class="stc-confirm-modal player-review-modal" data-player-modal hidden>
        <div class="stc-confirm-card player-review-card">
            <div class="stc-confirm-icon">OK</div>
            <div>
                <h2 data-review-title>Revisar jugador</h2>
                <p data-review-copy></p>
            </div>
            <form method="post" data-review-form>
                @csrf
                @method('PATCH')
                <input type="hidden" name="status" data-review-status>
                <input type="hidden" name="consent_status" data-review-consent>
                <div class="player-review-actions">
                    <button type="submit" data-review-consent-btn>Aprobar tutor / padre</button>
                    <button type="submit" data-review-approve>Aprobar jugador</button>
                    <button type="submit" data-review-enable>Habilitar para jugar</button>
                    <button type="button" data-review-close>Cerrar</button>
                </div>
            </form>
        </div>
    </div>
    <script>
        const modal = document.querySelector('[data-player-modal]');
        if (modal) {
            const form = modal.querySelector('[data-review-form]');
            const title = modal.querySelector('[data-review-title]');
            const copy = modal.querySelector('[data-review-copy]');
            const statusInput = modal.querySelector('[data-review-status]');
            const consentInput = modal.querySelector('[data-review-consent]');
            const close = () => modal.setAttribute('hidden', '');

            document.querySelectorAll('[data-player-review]').forEach((button) => {
                button.addEventListener('click', () => {
                    form.action = button.dataset.action;
                    title.textContent = button.dataset.name;
                    copy.textContent = `${button.dataset.guardian} (${button.dataset.relationship}) · ${button.dataset.consentLabel}. Ficha: ${button.dataset.statusLabel}. ${button.dataset.email} · ${button.dataset.phone}`;
                    statusInput.value = button.dataset.status;
                    consentInput.value = button.dataset.consent;
                    modal.removeAttribute('hidden');
                });
            });

            modal.querySelector('[data-review-consent-btn]')?.addEventListener('click', () => {
                consentInput.value = 'approved';
            });
            modal.querySelector('[data-review-approve]')?.addEventListener('click', () => {
                statusInput.value = 'approved';
            });
            modal.querySelector('[data-review-enable]')?.addEventListener('click', () => {
                statusInput.value = 'enabled';
            });
            modal.querySelector('[data-review-close]')?.addEventListener('click', close);
            modal.addEventListener('click', (event) => {
                if (event.target === modal) close();
            });
        }
    </script>
</x-layouts.stc>
