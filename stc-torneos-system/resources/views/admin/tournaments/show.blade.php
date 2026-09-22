<x-layouts.stc
    :title="$tournament->name.' | STC Torneos'"
    active="Torneos"
    :heading="$tournament->name"
    :subheading="'Paso 1 · '.$tournament->edition.' · '.$tournament->city.', '.$tournament->country.' · '.$tournament->starts_at->format('d/m/Y').' al '.$tournament->ends_at->format('d/m/Y')"
    :tournament="$tournament"
>
    @if (session('status'))
        <div class="system-alert">{{ session('status') }}</div>
    @endif

    @if ($tournament->isArchived())
        <div class="system-alert">Este torneo está archivado. No admite operación deportiva ordinaria.</div>
    @endif

    <section class="hero-panel tournament-hero">
        <div>
            <h2>{{ $tournament->name }}</h2>
            <div class="hero-buttons">
                <a href="{{ route('admin.tournaments.edit', $tournament) }}">Editar</a>
                @if (auth()->user()?->isSuperAdmin())
                    <form method="post" action="{{ route('admin.tournaments.duplicate', $tournament) }}">
                        @csrf
                        <button class="ghost-hero" type="submit">Duplicar</button>
                    </form>
                    <form method="post" action="{{ route('admin.tournaments.destroy', $tournament) }}" data-confirm="¿Eliminar {{ $tournament->name }}? Se borran categorías, equipos y partidos.">
                        @csrf
                        @method('DELETE')
                        <button class="ghost-hero" type="submit">Eliminar</button>
                    </form>
                @endif
                @if (! $tournament->isArchived())
                    <form method="post" action="{{ route('admin.tournaments.publish', $tournament) }}">
                        @csrf
                        @method('PATCH')
                        <button class="ghost-hero" type="submit">Publicar</button>
                    </form>
                    <form method="post" action="{{ route('admin.tournaments.finish', $tournament) }}">
                        @csrf
                        @method('PATCH')
                        <button class="ghost-hero" type="submit">Finalizar</button>
                    </form>
                    <form method="post" action="{{ route('admin.tournaments.archive', $tournament) }}" data-confirm="¿Archivar {{ $tournament->name }}? Queda sin operación deportiva ordinaria.">
                        @csrf
                        @method('PATCH')
                        <button class="ghost-hero" type="submit">Archivar</button>
                    </form>
                @endif
                <a href="{{ route('admin.tournaments.index') }}">Volver</a>
            </div>
        </div>
        <p>{{ $tournament->venue_name }} · Estado {{ $tournament->statusLabel() }} · {{ $tournament->visibility === 'public' ? 'Público' : 'Privado' }}</p>
        <figure class="trophy-card">
            <img src="{{ $tournament->logoUrl() }}" alt="{{ $tournament->name }}">
        </figure>
    </section>

    <section class="kpi-grid">
        @foreach ([['Categorías', $tournament->categories_count], ['Equipos', $tournament->teams_count], ['Partidos', $tournament->matches_count], ['Estado', $tournament->statusLabel()]] as [$label, $value])
            <article class="kpi-card">
                <strong>{{ $value }}</strong>
                <span>{{ $label }}</span>
            </article>
        @endforeach
    </section>

    <section class="dashboard-wide-grid">
        <article class="stc-card">
            <header>
                <div>
                    <p class="stc-eyebrow">Inscripciones</p>
                    <h3>Control de planteles</h3>
                </div>
            </header>
            @php
                $openCategories = $tournament->categories->filter(fn ($category) => $category->registrationsOpen())->count();
                $allOpen = $tournament->categories->isNotEmpty() && $openCategories === $tournament->categories->count();
            @endphp
            <p>
                Categorías con inscripciones abiertas:
                <strong>{{ $openCategories }} / {{ $tournament->categories->count() }}</strong>
            </p>
            <div class="hero-buttons">
                <form method="post" action="{{ route('admin.tournaments.registrations', $tournament) }}">
                    @csrf
                    @method('PATCH')
                    <input type="hidden" name="open" value="1">
                    <button type="submit" class="ghost-hero" @disabled($allOpen)>Abrir todas</button>
                </form>
                <form method="post" action="{{ route('admin.tournaments.registrations', $tournament) }}">
                    @csrf
                    @method('PATCH')
                    <input type="hidden" name="open" value="0">
                    <button type="submit" class="ghost-hero" @disabled($openCategories === 0)>Cerrar todas</button>
                </form>
            </div>
            <p class="category-rules-copy">Afecta a todas las categorías del torneo. Los delegados dejan de poder modificar planteles mientras estén cerradas.</p>
        </article>

        <article class="stc-card">
            <header>
                <div>
                    <p class="stc-eyebrow">Configuración</p>
                    <h3>Información institucional</h3>
                </div>
            </header>

            <div class="permission-grid">
                <div class="permission-box">
                    <strong>Inscripción</strong>
                    <span>{{ $tournament->registration_starts_at?->format('d/m/Y') ?? 'Sin inicio' }}</span>
                    <span>Cierre: {{ $tournament->registration_ends_at?->format('d/m/Y') ?? 'Sin cierre' }}</span>
                </div>
                <div class="permission-box">
                    <strong>Contacto</strong>
                    <span>{{ $tournament->contact_name ?? 'Sin contacto' }}</span>
                    <span>{{ $tournament->contact_email ?? $tournament->contact_phone ?? 'Sin datos' }}</span>
                </div>
                <div class="permission-box">
                    <strong>Zona horaria</strong>
                    <span>{{ $tournament->timezone }}</span>
                    <span>{{ $tournament->rules_url ? 'Reglamento configurado' : 'Sin reglamento' }}</span>
                </div>
                <div class="permission-box">
                    <strong>Información</strong>
                    <span>{{ $tournament->general_info ?? $tournament->description ?? 'Sin información general' }}</span>
                </div>
            </div>
        </article>

        <article class="stc-card">
            <header>
                <div>
                    <p class="stc-eyebrow">Categorías</p>
                    <h3>Competencias vinculadas</h3>
                </div>
                <a href="{{ route('admin.categories.index') }}">Gestionar</a>
            </header>

            <div class="permission-grid">
                @foreach ($tournament->categories as $category)
                    <div class="permission-box">
                        <x-entity-cell
                            :href="route('admin.categories.show', $category)"
                            :src="$category->bannerUrl()"
                            :alt="$category->name"
                        >
                            {{ $category->name }}
                            <x-slot:subtitle>{{ $category->birth_year }} · {{ $category->modalityLabel() }}</x-slot:subtitle>
                        </x-entity-cell>
                        <span>{{ $category->team_limit }} cupos · {{ $category->statusLabel() }}</span>
                        <span>{{ $category->registrationsOpen() ? 'Inscripciones abiertas' : 'Inscripciones cerradas' }}</span>
                        <a href="{{ route('admin.categories.show', $category) }}">Gestionar</a>
                    </div>
                @endforeach
            </div>
        </article>
    </section>

    <section class="dashboard-wide-grid">
        <article class="stc-card">
            <header>
                <div>
                    <p class="stc-eyebrow">Fixture</p>
                    <h3>Últimos partidos</h3>
                </div>
                <a href="{{ route('admin.fixture.index', ['tournament_id' => $tournament->id]) }}">Ver todos</a>
            </header>

            <div class="schedule-list">
                @foreach ($tournament->matches->take(5) as $match)
                    <div class="schedule-row">
                        <time>{{ $match->scheduled_at->format('H:i') }}</time>
                        <span>
                            <x-match-teams :match="$match" />
                            <small>{{ $match->field?->name }} · {{ $match->stage }}</small>
                        </span>
                        <mark class="status-pill status-{{ $match->status }}">{{ ucfirst($match->status) }}</mark>
                    </div>
                @endforeach
            </div>
        </article>
    </section>
</x-layouts.stc>
