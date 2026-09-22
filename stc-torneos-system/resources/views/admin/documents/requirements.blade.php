<x-layouts.stc
    title="Requisitos documentales | STC Torneos"
    active="Documentación"
    heading="Requisitos documentales"
    :subheading="$subheading"
>
    @if (session('status'))
        <div class="system-alert">{{ session('status') }}</div>
    @endif

    <a class="back-link" href="{{ route('admin.documents.index') }}">← Volver a la bandeja</a>

    <section class="delegation-form-card">
        <h3>Nuevo requisito</h3>
        <form method="post" action="{{ route('admin.documents.requirements.store') }}">
            @csrf
            <div class="ficha-row">
                <label>Tipo <input name="type" list="requirement-types" required placeholder="DNI frente"></label>
                <label>Torneo
                    <select name="tournament_id">
                        <option value="">Todos / sin recorte</option>
                        @foreach ($tournaments as $tournament)
                            <option value="{{ $tournament->id }}">{{ $tournament->name }}</option>
                        @endforeach
                    </select>
                </label>
                <label>Categoría
                    <select name="category_id">
                        <option value="">Todas las del torneo</option>
                        @foreach ($categories as $category)
                            <option value="{{ $category->id }}">{{ $category->name }}</option>
                        @endforeach
                    </select>
                </label>
                <label>Validez (días) <input type="number" name="validity_days" min="1" max="1460"></label>
            </div>
            <div class="ficha-row">
                <label><input type="checkbox" name="required" value="1" checked> Obligatorio</label>
                <label><input type="checkbox" name="has_expiration" value="1"> Tiene vencimiento</label>
                <label class="span-2">Notas <input name="notes" placeholder="Ej: renovar cada temporada"></label>
            </div>
            <datalist id="requirement-types">
                @foreach ($types as $type)
                    <option value="{{ $type }}"></option>
                @endforeach
            </datalist>
            <button class="ficha-save" type="submit">Guardar requisito</button>
        </form>
        <form method="post" action="{{ route('admin.documents.requirements.apply') }}" data-confirm="¿Crear documentos pendientes en las fichas según estos requisitos?">
            @csrf
            <button class="ficha-save" type="submit" style="margin-top: .8rem;">Aplicar a fichas existentes</button>
        </form>
    </section>

    <section class="sheets-list-card" style="margin-top: 1.2rem;">
        <h3>Requisitos cargados</h3>
        <div class="delegation-figma-table">
            <div class="table-head">
                <span>Tipo</span>
                <span>Alcance</span>
                <span>Obligación</span>
                <span>Vencimiento</span>
                <span>Acción</span>
            </div>
            @forelse ($requirements as $requirement)
                <div class="table-row">
                    <span><strong>{{ $requirement->type }}</strong></span>
                    <span>{{ $requirement->scopeLabel() }}</span>
                    <span>{{ $requirement->obligationLabel() }}</span>
                    <span>{{ $requirement->expirationLabel() }}</span>
                    <span>
                        @if ($requirement->notes)
                            <small>{{ $requirement->notes }}</small>
                        @endif
                        <form method="post" action="{{ route('admin.documents.requirements.update', $requirement) }}" style="margin-top:.4rem;">
                            @csrf
                            @method('PUT')
                            <input name="type" value="{{ $requirement->type }}" required>
                            <select name="tournament_id">
                                <option value="">Todos</option>
                                @foreach ($tournaments as $tournament)
                                    <option value="{{ $tournament->id }}" @selected((int) $requirement->tournament_id === (int) $tournament->id)>{{ $tournament->name }}</option>
                                @endforeach
                            </select>
                            <select name="category_id">
                                <option value="">Todas</option>
                                @foreach ($categories as $category)
                                    <option value="{{ $category->id }}" @selected((int) $requirement->category_id === (int) $category->id)>{{ $category->name }}</option>
                                @endforeach
                            </select>
                            <label><input type="checkbox" name="required" value="1" @checked($requirement->required)> Obligatorio</label>
                            <label><input type="checkbox" name="has_expiration" value="1" @checked($requirement->has_expiration)> Vence</label>
                            <input type="number" name="validity_days" min="1" max="1460" value="{{ $requirement->validity_days }}" placeholder="Días">
                            <input name="notes" value="{{ $requirement->notes }}" placeholder="Notas">
                            <button type="submit">Guardar</button>
                        </form>
                        <form method="post" action="{{ route('admin.documents.requirements.destroy', $requirement) }}" data-confirm="¿Eliminar este requisito?">
                            @csrf
                            @method('DELETE')
                            <button type="submit">Eliminar</button>
                        </form>
                    </span>
                </div>
            @empty
                <div class="category-empty-state"><strong>Todavía no hay requisitos. Cargá DNI, ficha médica o autorización por torneo o categoría.</strong></div>
            @endforelse
        </div>
    </section>
</x-layouts.stc>
