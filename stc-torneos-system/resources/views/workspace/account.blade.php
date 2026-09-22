<x-layouts.workspace
    :title="$title"
    heading="Mi cuenta"
    :subheading="$user->email"
    :category="$category"
    :tournament="$tournament"
    active="Cuenta"
>
    <section class="ws-ficha">
        <article class="ws-card">
            <p class="stc-eyebrow">Datos de acceso</p>
            <p class="ws-muted">Entrá con tu correo. Si te crearon el usuario, la primera clave es la de demo. Acá la cambiás.</p>
            <div class="ws-setup-grid">
                <label>Nombre <input type="text" value="{{ $user->name }}" disabled></label>
                <label>Correo <input type="email" value="{{ $user->email }}" disabled></label>
                <label>Rol <input type="text" value="{{ $user->roleLabel() }}" disabled></label>
            </div>
        </article>

        <article class="ws-card">
            <p class="stc-eyebrow">Cambiar clave</p>
            <form class="ws-form" method="post" action="{{ route('workspace.account.password') }}">
                @csrf
                @method('PATCH')
                <label>Clave actual <input type="password" name="current_password" required autocomplete="current-password"></label>
                <label>Clave nueva <input type="password" name="password" required minlength="8" autocomplete="new-password"></label>
                <label>Repetir clave nueva <input type="password" name="password_confirmation" required minlength="8" autocomplete="new-password"></label>
                <div class="ws-form-actions">
                    <button type="submit" class="ws-btn">Guardar clave</button>
                </div>
            </form>
        </article>

        <article class="ws-card">
            <p class="stc-eyebrow">Qué podés hacer en Operación</p>
            <p class="ws-muted">Según tu rol <strong>{{ $user->roleLabel() }}</strong>:</p>
            <ul class="ws-muted" style="margin: 0.75rem 0 0 1.1rem;">
                @foreach ($access->capabilityLines() as $line)
                    <li style="margin-bottom: 0.35rem;">{{ $line }}</li>
                @endforeach
            </ul>
            @if ($access->canAccessAdminWeb())
                <p class="ws-muted" style="margin-top: 1rem;">También podés entrar al <a href="{{ route('dashboard') }}">Admin Web</a> (módulos avanzados).</p>
            @endif
        </article>
    </section>
</x-layouts.workspace>
