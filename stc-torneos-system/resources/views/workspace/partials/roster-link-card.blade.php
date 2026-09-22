@if ($canShareRoster ?? false)
    <article class="ws-card ws-inscriptions-control">
        <p class="stc-eyebrow">Enlace para cargar plantel</p>
        <p class="ws-muted">Compartí este enlace para que carguen jugadores <strong>sin entrar al panel</strong> (como el sistema viejo).</p>
        @if ($rosterLink ?? null)
            <p class="ws-muted">Estado: <strong>{{ $rosterLink->familyStatusLabel() }}</strong>
                @if ($rosterLink->expires_at)
                    · vence {{ $rosterLink->expires_at->format('d/m/Y') }}
                @endif
            </p>
            <div class="ws-team-link-row">
                <input type="text" readonly value="{{ $rosterLink->publicUrl() }}" id="roster-link-url" class="ws-roster-link-input">
                <button type="button" class="ws-btn" onclick="navigator.clipboard.writeText(document.getElementById('roster-link-url').value).then(() => alert('Enlace copiado'))">Copiar enlace</button>
                <form method="post" action="{{ route('workspace.categories.teams.roster-link.regenerate', [$category, $team]) }}">
                    @csrf
                    <button type="submit" class="ws-btn ghost">Regenerar</button>
                </form>
                <form method="post" action="{{ route('workspace.categories.teams.roster-link.invalidate', [$category, $team]) }}">
                    @csrf
                    @method('PATCH')
                    <button type="submit" class="ws-btn danger">Invalidar</button>
                </form>
            </div>
        @else
            <form method="post" action="{{ route('workspace.categories.teams.roster-link', [$category, $team]) }}">
                @csrf
                <button type="submit" class="ws-btn">Generar enlace</button>
            </form>
        @endif
    </article>
@elseif ($rosterLink ?? null)
    <article class="ws-card">
        <p class="stc-eyebrow">Enlace de plantel (solo lectura)</p>
        <p class="ws-muted">Ya hay un enlace activo. Podés copiarlo; solo el delegado o la organización pueden regenerarlo.</p>
        <div class="ws-team-link-row">
            <input type="text" readonly value="{{ $rosterLink->publicUrl() }}" id="roster-link-url-readonly" class="ws-roster-link-input">
            <button type="button" class="ws-btn ghost" onclick="navigator.clipboard.writeText(document.getElementById('roster-link-url-readonly').value).then(() => alert('Enlace copiado'))">Copiar</button>
        </div>
    </article>
@else
    <article class="ws-card">
        <p class="stc-eyebrow">Enlace para cargar plantel</p>
        <p class="ws-muted">Tu rol (<strong>{{ auth()->user()?->roleLabel() }}</strong>) no puede generar el enlace. Entrá como <strong>delegado del club</strong> o <strong>admin del torneo</strong> para crearlo.</p>
        <p class="ws-muted">Demo: <code>delegado@stctorneos.demo</code> o <code>torneo@stctorneos.demo</code> · clave <code>stcdemo</code></p>
    </article>
@endif
