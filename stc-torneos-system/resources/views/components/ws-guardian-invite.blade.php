@props([
    'player',
    'category',
    'canEdit' => false,
])

@php
    $invitation = $player->latestGuardianInvitation();
    $certificate = $player->guardianCertificate();
    $signed = $player->guardianConfirmationSigned();
@endphp

<div class="ws-tutor-confirm" id="confirmacion-tutor">
    <div class="ws-tutor-confirm-head">
        <p class="stc-eyebrow">Confirmación del tutor</p>
        <span @class(['ws-tutor-pill', 'is-done' => $signed])>{{ $signed ? 'Firmado' : 'Pendiente' }}</span>
    </div>

    <p class="ws-tutor-confirm-links">
        @if ($certificate?->fileUrl())
            <a href="{{ $certificate->fileUrl() }}" target="_blank" rel="noopener">Constancia</a>
        @endif
        @if ($invitation && ($invitation->isUsable() || $invitation->canViewFicha()))
            @if ($certificate?->fileUrl())
                <span aria-hidden="true">·</span>
            @endif
            <a href="{{ $invitation->publicUrl() }}" target="_blank" rel="noopener noreferrer">Enlace de confirmación</a>
        @endif
    </p>

    @if ($invitation && ($invitation->isUsable() || $invitation->canViewFicha()))
        <div class="ws-tutor-share">
            <label class="ws-tutor-share-label" for="guardian-link-{{ $player->id }}">Link para el tutor (sin login)</label>
            <div class="ws-tutor-share-row">
                <input id="guardian-link-{{ $player->id }}" type="text" readonly value="{{ $invitation->publicUrl() }}" data-guardian-link>
                <button type="button" class="ws-btn ghost" data-copy-guardian-link data-copy-label="Copiar link">Copiar link</button>
            </div>
            @if ($player->whatsappShareUrl())
                <p class="ws-muted ws-tutor-share-hint">
                    También podés enviarlo por
                    <a href="{{ $player->whatsappShareUrl() }}" target="_blank" rel="noopener noreferrer">WhatsApp</a>
                    o reenviar el mail al generar el enlace.
                </p>
            @endif
        </div>
    @endif

    @if ($canEdit && ! $signed)
        <form class="ws-tutor-confirm-form" method="post" action="{{ route('workspace.categories.players.invite', [$category, $player]) }}">
            @csrf
            <label>
                <span class="sr-only">Email del tutor</span>
                <input type="email" name="email" value="{{ old('email', $player->guardian?->email) }}" placeholder="Email del tutor" required>
            </label>
            <button type="submit" class="ws-btn ghost">Generar enlace</button>
        </form>
        @error('email')
            <p class="ws-alert" role="alert">{{ $message }}</p>
        @enderror
        @error('guardian_email')
            <p class="ws-alert" role="alert">{{ $message }}</p>
        @enderror
        <p class="ws-muted ws-tutor-share-hint">Usá el correo real del padre/madre (no uno de admin o delegado). Al generar el enlace, el tutor entra al panel con clave inicial <strong>stctutor</strong>. Si el mail no llega, copiá el link o mandalo por WhatsApp.</p>
    @elseif ($canEdit && $invitation && $invitation->isUsable())
        <div class="ws-tutor-confirm-form">
            <form method="post" action="{{ route('workspace.categories.players.invite.regenerate', [$category, $player]) }}">
                @csrf
                <button type="submit" class="ws-btn ghost">Regenerar enlace</button>
            </form>
        </div>
    @endif
</div>
