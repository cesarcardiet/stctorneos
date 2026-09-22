<x-layouts.workspace
    :title="$title"
    :heading="$heading"
    :subheading="$subheading"
    :active="$active"
>
    <p style="margin-bottom: 1rem;">
        <a href="{{ route('workspace.tutor.home') }}" class="ws-btn ghost">← Volver a Mis jugadores</a>
    </p>

    <section class="ws-card ws-tutor-ficha-card">
        <div class="ws-tutor-ficha-head">
            <p class="stc-eyebrow">Ficha del jugador · Tutor</p>
            <p class="ws-muted">
                Estado:
                <strong>{{ $locked ? 'En revisión' : $invitation->familyStatusLabel() }}</strong>
                · {{ $player->team?->tournament?->name }}
            </p>
            @if ($errors->any())
                <div class="login-alert" style="margin-top: .75rem;">
                    @foreach ($errors->all() as $error)
                        <div>{{ $error }}</div>
                    @endforeach
                </div>
            @endif
            @if (session('status'))
                <div class="login-alert" style="margin-top: .75rem;">{{ session('status') }}</div>
            @endif
            @if ($locked)
                <p class="ws-muted">La ficha ya fue enviada. Solo podés verla. Para cambios, pedí al administrador o delegado.</p>
            @else
                <p class="ws-muted">Completá datos, subí archivos y firmá las autorizaciones legales. Es la misma ficha del enlace por WhatsApp o correo.</p>
            @endif
        </div>

        @include('ficha.partials.form-body', ['loadFichaAssets' => true, 'panelClass' => 'ficha-wizard-panel'])
    </section>
</x-layouts.workspace>
