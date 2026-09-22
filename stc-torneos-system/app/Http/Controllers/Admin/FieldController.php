<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Field;
use App\Models\FieldTimeSlot;
use App\Models\Venue;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class FieldController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();
        $query = Field::query()
            ->accessibleTo($user)
            ->with(['venue.tournament', 'matches.homeTeam', 'matches.awayTeam']);
        $this->applyFilters($request, $query);
        $fields = $query->orderBy('name')->get();
        $venues = Venue::query()->accessibleTo($user)->with(['fields.matches'])->orderBy('name')->get();

        $conflictCount = $fields->filter(fn (Field $field) => $field->hasConflict())->count();

        return view('admin.fields.index', [
            'fields' => $fields,
            'venues' => $venues,
            'stats' => [
                [$venues->count(), 'Sedes', 'blue'],
                [$fields->count(), 'Canchas', 'cyan'],
                [$fields->filter(fn (Field $field) => $field->operationalStatus() === 'available')->count(), 'Disponibles', 'green'],
                [$conflictCount, 'Conflictos', 'orange'],
            ],
            'filters' => [
                'search' => $request->string('search')->toString(),
                'status' => $request->string('status', 'all')->toString(),
                'venue_id' => $request->string('venue_id')->toString(),
                'tournament_id' => $request->string('tournament_id')->toString(),
            ],
            'accessibleTournaments' => $user->accessibleTournaments(),
            'subheading' => $user->canAccessAllTournaments()
                ? 'Administración de canchas, sedes, horarios y disponibilidad.'
                : 'Sedes y canchas de tus torneos asignados.',
        ]);
    }

    public function create(Request $request): View
    {
        $tournamentId = $request->user()?->accessibleTournaments()->first()?->id;

        return $this->form(new Field([
            'status' => 'available',
            'surface' => 'Césped sintético',
            'modality' => 'Fútbol 7',
            'lighting' => true,
            'opens_at' => '08:00',
            'closes_at' => '20:00',
        ]), new Venue([
            'tournament_id' => $tournamentId,
            'city' => 'Santa Teresita',
            'status' => 'active',
        ]), 'POST', route('admin.fields.store'), 'Crear / Editar Sede o Cancha');
    }

    public function store(Request $request): RedirectResponse
    {
        $field = $this->saveField($request);
        $this->syncTimeSlots($request, $field);
        $this->audit($field, 'create', 'Cancha creada: '.$field->name.' en '.$field->venue?->name);

        return redirect()
            ->route('admin.fields.show', $field)
            ->with('status', 'Sede y cancha guardadas correctamente.');
    }

    public function show(Field $field): View
    {
        $this->assertFieldAccess($field);
        $field->load(['venue.fields.matches', 'venue.tournament', 'matches.homeTeam', 'matches.awayTeam', 'matches.category']);

        $venueFields = $field->venue?->fields ?? collect();
        $matchesOnField = $field->matches->sortBy('scheduled_at');
        $todayMatches = $matchesOnField->filter(fn ($match) => $match->scheduled_at?->isSameDay(now()));
        $kpiDay = $todayMatches->isNotEmpty()
            ? now()
            : $matchesOnField->first(fn ($match) => $match->scheduled_at)?->scheduled_at;
        $matchesTodayCount = $kpiDay
            ? $matchesOnField->filter(fn ($match) => $match->scheduled_at?->isSameDay($kpiDay))->count()
            : 0;

        $schedule = $field->upcomingMatches();
        if ($schedule->isEmpty()) {
            $schedule = $matchesOnField->values();
        }

        return view('admin.fields.show', [
            'field' => $field,
            'venueStats' => [
                [$venueFields->count(), 'Canchas', 'cyan'],
                [$venueFields->filter(fn (Field $item) => $item->operationalStatus() === 'available')->count(), 'Disponibles', 'green'],
                [$matchesTodayCount, 'Partidos hoy', 'orange'],
            ],
            'schedule' => $schedule->take(6),
            'venueFields' => $venueFields,
        ]);
    }

    public function edit(Field $field): View
    {
        $this->assertFieldAccess($field);
        $field->load('venue');
        $slots = $this->slotDefaults($field);

        return $this->form(
            $field,
            $field->venue ?? new Venue(['status' => 'active']),
            'PUT',
            route('admin.fields.update', $field),
            'Crear / Editar Sede o Cancha',
            $slots
        );
    }

    public function update(Request $request, Field $field): RedirectResponse
    {
        $this->assertFieldAccess($field);
        $field = $this->saveField($request, $field);
        $this->syncTimeSlots($request, $field);
        $this->audit($field, 'update', 'Cancha actualizada: '.$field->name);

        return redirect()
            ->route('admin.fields.show', $field)
            ->with('status', 'Sede y cancha actualizadas.');
    }

    public function updateStatus(Request $request, Field $field): RedirectResponse
    {
        $this->assertFieldAccess($field);
        $data = $request->validate([
            'status' => ['required', 'string', 'in:available,occupied,blocked,maintenance,unavailable'],
        ]);

        $field->update($data);
        $this->audit($field, 'status_update', 'Estado de cancha actualizado a '.$field->statusLabel().'.');

        return back()->with('status', 'Estado de la cancha actualizado.');
    }

    public function destroy(Field $field): RedirectResponse
    {
        $this->assertFieldAccess($field);
        if ($field->matches()->exists()) {
            return back()->with('status', 'No se puede eliminar: la cancha tiene partidos en el fixture.');
        }

        $this->audit($field, 'delete', 'Cancha eliminada: '.$field->name);
        $field->delete();

        return redirect()
            ->route('admin.fields.index')
            ->with('status', 'Cancha eliminada.');
    }

    private function form(Field $field, Venue $venue, string $method, string $url, string $title, ?array $slots = null): View
    {
        $user = auth()->user();
        $venues = Venue::query()->accessibleTo($user)->orderBy('name')->get();

        return view('admin.fields.form', [
            'field' => $field,
            'venue' => $venue,
            'method' => $method,
            'url' => $url,
            'title' => $title,
            'venues' => $venues,
            'slots' => $slots ?? $this->slotDefaults($field),
            'accessibleTournaments' => $user->accessibleTournaments(),
            'venueOptions' => $venues->mapWithKeys(fn (Venue $item) => [
                $item->id => $item->only(['name', 'city', 'address', 'map_url', 'status', 'tournament_id']),
            ]),
        ]);
    }

    /**
     * @return array<int, array{opens_at: string, closes_at: string, closed: bool}>
     */
    private function slotDefaults(Field $field): array
    {
        $existing = $field->exists
            ? $field->timeSlots()->get()->keyBy('weekday')
            : collect();
        $open = $field->opens_at ? substr((string) $field->opens_at, 0, 5) : '08:00';
        $close = $field->closes_at ? substr((string) $field->closes_at, 0, 5) : '20:00';
        $slots = [];

        foreach (FieldTimeSlot::weekdayLabels() as $day => $label) {
            $row = $existing->get($day);
            $slots[$day] = [
                'label' => $label,
                'opens_at' => $row && $row->opens_at ? substr((string) $row->opens_at, 0, 5) : $open,
                'closes_at' => $row && $row->closes_at ? substr((string) $row->closes_at, 0, 5) : $close,
                'closed' => (bool) ($row?->closed ?? false),
            ];
        }

        return $slots;
    }

    private function syncTimeSlots(Request $request, Field $field): void
    {
        $incoming = $request->input('slots', []);
        if (! is_array($incoming) || $incoming === []) {
            return;
        }

        foreach (array_keys(FieldTimeSlot::weekdayLabels()) as $day) {
            $row = is_array($incoming[$day] ?? null) ? $incoming[$day] : [];
            FieldTimeSlot::query()->updateOrCreate(
                ['field_id' => $field->id, 'weekday' => $day],
                [
                    'opens_at' => $row['opens_at'] ?? $field->opens_at,
                    'closes_at' => $row['closes_at'] ?? $field->closes_at,
                    'closed' => ! empty($row['closed']),
                ]
            );
        }
    }

    private function saveField(Request $request, ?Field $field = null): Field
    {
        $data = $request->validate([
            'venue_id' => ['nullable', 'exists:venues,id'],
            'tournament_id' => ['required', 'exists:tournaments,id'],
            'venue_name' => ['required', 'string', 'max:180'],
            'city' => ['required', 'string', 'max:120'],
            'address' => ['nullable', 'string', 'max:255'],
            'map_url' => ['nullable', 'string', 'max:500'],
            'venue_status' => ['required', 'string', 'in:active,inactive'],
            'name' => ['required', 'string', 'max:120'],
            'surface' => ['required', 'string', 'max:80'],
            'modality' => ['nullable', 'string', 'max:80'],
            'opens_at' => ['nullable', 'string', 'max:8'],
            'closes_at' => ['nullable', 'string', 'max:8'],
            'status' => ['required', 'string', 'in:available,occupied,blocked,maintenance,unavailable'],
            'lighting' => ['nullable', 'boolean'],
            'notes' => ['nullable', 'string', 'max:1200'],
        ]);

        abort_unless(
            $request->user()?->canAccessTournament((int) $data['tournament_id']),
            403,
            'Este torneo no está dentro de tu alcance.'
        );

        $venue = ($data['venue_id'] ?? null)
            ? Venue::findOrFail($data['venue_id'])
            : Venue::query()->firstOrNew([
                'name' => $data['venue_name'],
                'tournament_id' => $data['tournament_id'],
            ]);

        abort_unless(
            $request->user()?->canAccessTournament($venue->tournament_id ? (int) $venue->tournament_id : (int) $data['tournament_id']),
            403,
            'Esta sede no está dentro de tu alcance.'
        );

        $venue->fill([
            'tournament_id' => $data['tournament_id'],
            'name' => $data['venue_name'],
            'city' => $data['city'],
            'address' => $data['address'] ?? null,
            'map_url' => $data['map_url'] ?? $venue->map_url,
            'status' => $data['venue_status'],
        ])->save();

        $payload = [
            'venue_id' => $venue->id,
            'name' => $data['name'],
            'surface' => $data['surface'],
            'modality' => $data['modality'] ?? null,
            'opens_at' => $data['opens_at'] ?? null,
            'closes_at' => $data['closes_at'] ?? null,
            'status' => $data['status'],
            'lighting' => $request->boolean('lighting'),
            'notes' => $data['notes'] ?? null,
        ];

        if ($field) {
            $field->update($payload);

            return $field->fresh('venue');
        }

        return Field::create($payload)->load('venue');
    }

    private function applyFilters(Request $request, $query): void
    {
        $user = $request->user();

        $query
            ->when($request->filled('search'), function ($query) use ($request) {
                $search = $request->string('search')->toString();
                $query->where(function ($query) use ($search) {
                    $query
                        ->where('name', 'like', "%{$search}%")
                        ->orWhere('surface', 'like', "%{$search}%")
                        ->orWhere('modality', 'like', "%{$search}%")
                        ->orWhereHas('venue', fn ($query) => $query
                            ->where('name', 'like', "%{$search}%")
                            ->orWhere('city', 'like', "%{$search}%")
                            ->orWhere('address', 'like', "%{$search}%"));
                });
            })
            ->when($request->filled('venue_id'), fn ($query) => $query->where('venue_id', $request->integer('venue_id')))
            ->when($request->filled('tournament_id'), function ($query) use ($request, $user) {
                $tournamentId = $request->integer('tournament_id');
                abort_unless($user?->canAccessTournament($tournamentId), 403, 'Este torneo no está dentro de tu alcance.');
                $query->whereHas('venue', fn ($query) => $query->where('tournament_id', $tournamentId));
            })
            ->when($request->filled('status') && $request->string('status')->toString() !== 'all', function ($query) use ($request) {
                $status = $request->string('status')->toString();
                if ($status === 'occupied') {
                    $query->where(function ($query) {
                        $query->where('status', 'occupied')
                            ->orWhereHas('matches', fn ($query) => $query->where('status', 'live'));
                    });
                } elseif ($status === 'available') {
                    $query->where('status', 'available')->whereDoesntHave('matches', fn ($query) => $query->where('status', 'live'));
                } elseif ($status === 'conflict') {
                    $query->where(function ($query) {
                        $query->where('status', 'maintenance')
                            ->orWhereHas('matches', fn ($query) => $query->whereIn('status', ['scheduled', 'rescheduled', 'live']));
                    });
                } else {
                    $query->where('status', $status);
                }
            });
    }

    private function assertFieldAccess(Field $field): void
    {
        abort_unless(
            auth()->user()?->canAccessTournament($field->venue?->tournament_id ? (int) $field->venue->tournament_id : null),
            403,
            'Esta cancha no está dentro de tu alcance.'
        );
    }

    private function audit(Field $field, string $action, string $description): void
    {
        AuditLog::create([
            'user_id' => auth()->id(),
            'module' => 'Campos',
            'action' => $action,
            'description' => $description,
            'auditable_type' => Field::class,
            'auditable_id' => $field->id,
            'metadata' => [
                'field' => $field->name,
                'venue' => $field->venue?->name,
                'status' => $field->status,
            ],
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ]);
    }
}
