<x-layouts.stc
    title="Sin permisos | STC Torneos"
    active="Dashboard"
    heading="Sin permisos"
    subheading="Este módulo no está habilitado para tu rol."
>
    <section class="users-form-card forbidden-card">
        <h3>No tenés permiso para este módulo.</h3>
        <p>
            Tu rol ({{ auth()->user()?->roleLabel() ?? 'sin rol' }}) no incluye esta operación.
            Si necesitás acceso, pedilo a un Super Admin.
        </p>
        <a class="primary-action" href="{{ route('workspace.home') }}">Volver a operación</a>
    </section>
</x-layouts.stc>
