<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Tournament;
use App\Services\RegistrationControl;
use App\Services\TournamentPurger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class TournamentController extends Controller
{
    public function index(Request $request): View
    {
        $filters = [
            'search' => $request->string('search')->toString(),
            'status' => $request->string('status', 'all')->toString(),
            'venue' => $request->string('venue')->toString(),
            'period_from' => $request->string('period_from')->toString(),
            'period_to' => $request->string('period_to')->toString(),
            'filter' => $request->string('filter', 'all')->toString(),
        ];

        $query = Tournament::query()
            ->withCount(['categories', 'teams', 'matches'])
            ->latest('starts_at');

        if (! $request->user()->canAccessAllTournaments()) {
            $query->whereIn('id', $request->user()->assignedTournamentIds() ?: [0]);
        }

        if ($filters['search'] !== '') {
            $search = $filters['search'];
            $query->where(function ($query) use ($search) {
                $query
                    ->where('name', 'like', "%{$search}%")
                    ->orWhere('edition', 'like', "%{$search}%")
                    ->orWhere('city', 'like', "%{$search}%")
                    ->orWhere('country', 'like', "%{$search}%")
                    ->orWhere('location', 'like', "%{$search}%")
                    ->orWhere('venue_name', 'like', "%{$search}%");
            });
        }

        if ($filters['status'] !== '' && $filters['status'] !== 'all') {
            $query->where('status', $filters['status']);
        }

        if ($filters['venue'] !== '') {
            $query->where(function ($query) use ($filters) {
                $query
                    ->where('venue_name', 'like', '%'.$filters['venue'].'%')
                    ->orWhere('city', 'like', '%'.$filters['venue'].'%');
            });
        }

        if ($filters['period_from'] !== '') {
            $query->whereDate('ends_at', '>=', $filters['period_from']);
        }

        if ($filters['period_to'] !== '') {
            $query->whereDate('starts_at', '<=', $filters['period_to']);
        }

        match ($filters['filter']) {
            'upcoming' => $query->whereIn('status', ['draft', 'registration', 'preparation']),
            'live' => $query->where('status', 'in_progress'),
            'finished' => $query->whereIn('status', ['finished', 'archived']),
            default => null,
        };

        $tournaments = $query->get();

        $allTournaments = Tournament::query()
            ->withCount(['categories', 'teams', 'matches'])
            ->when(
                ! $request->user()->canAccessAllTournaments(),
                fn ($query) => $query->whereIn('id', $request->user()->assignedTournamentIds() ?: [0])
            )
            ->get();

        $stats = [
            ['Torneos operativos', $tournaments->whereIn('status', ['registration', 'preparation', 'in_progress'])->count()],
            ['Equipos vinculados', $tournaments->sum('teams_count')],
            ['Categorías', $tournaments->sum('categories_count')],
            ['Partidos cargados', $tournaments->sum('matches_count')],
        ];

        $summary = [
            ['Activos', $allTournaments->whereIn('status', ['registration', 'preparation', 'in_progress'])->count()],
            ['Inscripciones', $allTournaments->where('status', 'registration')->count()],
            ['Finalizados', $allTournaments->where('status', 'finished')->count()],
            ['Borradores', $allTournaments->where('status', 'draft')->count()],
        ];

        $filterOptions = [
            'all' => 'Todos',
            'upcoming' => 'Próximos',
            'live' => 'En juego',
            'finished' => 'Finalizados',
        ];

        return view('admin.tournaments.index', [
            'filter' => $filters['filter'],
            'filters' => $filters,
            'filterOptions' => $filterOptions,
            'stats' => $stats,
            'summary' => $summary,
            'tournaments' => $tournaments,
            'canCreateTournaments' => $request->user()->isSuperAdmin(),
        ]);
    }

    public function create(): View
    {
        $this->assertCanCreate();

        return view('admin.tournaments.form', [
            'method' => 'POST',
            'tournament' => new Tournament(['status' => 'draft']),
            'title' => 'Nuevo torneo',
            'url' => route('admin.tournaments.store'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->assertCanCreate();
        $data = $this->tournamentAttributes($request);
        $data['location'] = $this->locationFrom($data);
        $data['slug'] = $this->uniqueSlug($data['name']);

        $tournament = Tournament::create($data);
        $this->persistLogo($request, $tournament);

        $this->audit('create', $tournament, 'Torneo creado desde Admin Web.');

        return redirect()
            ->route('workspace.tournaments.show', $tournament)
            ->with('status', 'Torneo creado en estado Borrador.');
    }

    public function show(Tournament $tournament): View
    {
        $this->assertTournamentAccess($tournament);
        $tournament->loadCount(['categories', 'teams', 'matches']);
        $tournament->load(['categories', 'matches.homeTeam', 'matches.awayTeam', 'matches.field']);

        return view('admin.tournaments.show', compact('tournament'));
    }

    public function edit(Tournament $tournament): View
    {
        $this->assertTournamentAccess($tournament);
        return view('admin.tournaments.form', [
            'method' => 'PUT',
            'tournament' => $tournament,
            'title' => 'Editar torneo',
            'url' => route('admin.tournaments.update', $tournament),
        ]);
    }

    public function update(Request $request, Tournament $tournament): RedirectResponse
    {
        $this->assertTournamentAccess($tournament);
        $data = $this->tournamentAttributes($request);
        $data['location'] = $this->locationFrom($data);
        $data['slug'] = $tournament->slug ?: $this->uniqueSlug($data['name']);

        $tournament->update($data);
        $this->persistLogo($request, $tournament);

        $this->audit('update', $tournament, 'Torneo actualizado desde Admin Web.');

        return redirect()
            ->route('workspace.tournaments.show', $tournament)
            ->with('status', 'Torneo actualizado correctamente.');
    }

    public function destroy(Tournament $tournament, TournamentPurger $purger): RedirectResponse
    {
        $this->assertCanCreate();
        $this->assertTournamentAccess($tournament);
        $this->audit('delete', $tournament, 'Torneo eliminado: '.$tournament->name);
        $purger->delete($tournament);

        return redirect()
            ->route('admin.tournaments.index')
            ->with('status', 'Torneo eliminado correctamente.');
    }

    public function duplicate(Tournament $tournament): RedirectResponse
    {
        $this->assertCanCreate();
        $this->assertTournamentAccess($tournament);

        $copy = $tournament->replicate();
        $copy->name = 'Copia de '.$tournament->name;
        $copy->slug = $this->uniqueSlug($copy->name);
        $copy->status = 'draft';
        $copy->visibility = 'private';
        $copy->save();

        $this->audit('duplicate', $copy, 'Torneo duplicado desde '.$tournament->name.'.');

        return redirect()
            ->route('admin.tournaments.edit', $copy)
            ->with('status', 'Torneo duplicado en estado Borrador.');
    }

    public function publish(Tournament $tournament): RedirectResponse
    {
        $this->assertTournamentAccess($tournament);
        abort_if($tournament->isArchived(), 422, 'Un torneo archivado no admite publicación.');

        $blockers = $tournament->publishBlockers();
        if ($blockers !== []) {
            return back()->with('status', 'No se puede publicar. Completá: '.implode(', ', $blockers).'.');
        }

        $tournament->update([
            'visibility' => 'public',
            'status' => $tournament->status === 'draft' ? 'registration' : $tournament->status,
        ]);

        $this->audit('publish', $tournament, 'Torneo publicado.');

        return back()->with('status', 'Torneo publicado correctamente.');
    }

    public function finish(Tournament $tournament): RedirectResponse
    {
        $this->assertTournamentAccess($tournament);
        abort_if($tournament->isArchived(), 422, 'Un torneo archivado no admite operación deportiva ordinaria.');

        $tournament->update(['status' => 'finished']);
        $this->audit('finish', $tournament, 'Torneo finalizado.');

        return back()->with('status', 'Torneo finalizado.');
    }

    public function archive(Tournament $tournament): RedirectResponse
    {
        $this->assertTournamentAccess($tournament);

        $tournament->update([
            'status' => 'archived',
            'visibility' => 'private',
        ]);
        $this->audit('archive', $tournament, 'Torneo archivado. Queda sin operación deportiva ordinaria.');

        return back()->with('status', 'Torneo archivado. Sin operación deportiva ordinaria.');
    }

    public function toggleRegistrations(Request $request, Tournament $tournament): RedirectResponse
    {
        $this->assertTournamentAccess($tournament);

        $request->validate([
            'open' => ['required', 'boolean'],
        ]);

        $open = $request->boolean('open');
        RegistrationControl::setTournamentOpen($tournament, $open);

        $this->audit(
            'update',
            $tournament,
            ($open ? 'Inscripciones abiertas' : 'Inscripciones cerradas').' en todas las categorías de '.$tournament->name.'.'
        );

        return back()->with(
            'status',
            $open
                ? 'Inscripciones abiertas en todas las categorías del torneo.'
                : 'Inscripciones cerradas en todas las categorías del torneo.'
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function validatedData(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'edition' => ['required', 'string', 'max:120'],
            'country' => ['required', 'string', 'max:120'],
            'city' => ['required', 'string', 'max:120'],
            'venue_name' => ['required', 'string', 'max:255'],
            'timezone' => ['required', 'string', 'max:120'],
            'starts_at' => ['required', 'date'],
            'ends_at' => ['required', 'date', 'after_or_equal:starts_at'],
            'registration_starts_at' => ['nullable', 'date'],
            'registration_ends_at' => ['nullable', 'date', 'after_or_equal:registration_starts_at'],
            'status' => ['required', 'string', 'in:draft,registration,preparation,in_progress,finished,archived'],
            'visibility' => ['required', 'string', 'in:private,public'],
            'description' => ['nullable', 'string', 'max:1000'],
            'contact_name' => ['nullable', 'string', 'max:160'],
            'contact_email' => ['nullable', 'email', 'max:160'],
            'contact_phone' => ['nullable', 'string', 'max:80'],
            'rules_url' => ['nullable', 'string', 'max:1000'],
            'general_info' => ['nullable', 'string', 'max:1200'],
            'shield_file' => ['nullable', 'file', 'image', 'max:8192'],
            'shield_data' => ['nullable', 'string', 'max:8000000'],
            'logo_file' => ['nullable', 'file', 'image', 'max:8192'],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function tournamentAttributes(Request $request): array
    {
        $data = $this->validatedData($request);
        unset($data['shield_file'], $data['shield_data'], $data['logo_file']);

        return $data;
    }

    private function persistLogo(Request $request, Tournament $tournament): void
    {
        $hasImage = $request->filled('shield_data')
            || $request->hasFile('shield_file')
            || $request->hasFile('logo_file');

        if (! $hasImage) {
            return;
        }

        if (! $tournament->applyLogoFromRequest($request)) {
            throw ValidationException::withMessages([
                'shield_file' => 'No pudimos guardar la imagen del torneo. Probá con PNG, JPG o WebP.',
            ]);
        }
    }

    /**
     * @param array<string, mixed> $data
     */
    private function locationFrom(array $data): string
    {
        return trim($data['city'].', '.$data['country']);
    }

    private function uniqueSlug(string $name): string
    {
        $slug = Str::slug($name);
        $candidate = $slug;
        $counter = 2;

        while (Tournament::where('slug', $candidate)->exists()) {
            $candidate = "{$slug}-{$counter}";
            $counter++;
        }

        return $candidate;
    }

    private function assertCanCreate(): void
    {
        abort_unless(auth()->user()?->isSuperAdmin(), 403, 'Solo el Admin General puede crear, duplicar o eliminar torneos.');
    }

    private function assertTournamentAccess(Tournament $tournament): void
    {
        abort_unless(auth()->user()?->canAccessTournament((int) $tournament->id), 403, 'Este torneo no está dentro de tu alcance.');
    }

    private function audit(string $action, Tournament $tournament, string $description): void
    {
        AuditLog::create([
            'user_id' => auth()->id(),
            'module' => 'Torneos',
            'action' => $action,
            'description' => $description,
            'auditable_type' => Tournament::class,
            'auditable_id' => $tournament->id,
            'metadata' => ['name' => $tournament->name, 'status' => $tournament->status],
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'created_at' => now(),
        ]);
    }
}
