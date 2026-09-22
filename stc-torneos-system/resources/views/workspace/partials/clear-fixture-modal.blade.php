<x-ws-modal id="clear-fixture" title="Borrar todos los partidos">
    <x-ws-form :action="route('workspace.categories.matches.destroy-all', $category)" submit="Borrar todo">
        @method('DELETE')
        <p>Esto saca <strong>todos</strong> los partidos de <strong>{{ $category->name }}</strong>: fixture, clasificación y planillas.</p>
        <p class="ws-muted">No toca equipos ni grupos. Sirve para armar el fixture de nuevo.</p>
        <label>Escribí BORRAR para confirmar
            <input type="text" name="confirm" placeholder="BORRAR" autocomplete="off" required>
        </label>
    </x-ws-form>
</x-ws-modal>
