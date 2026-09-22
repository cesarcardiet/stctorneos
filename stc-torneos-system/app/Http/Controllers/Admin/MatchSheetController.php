<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Field;
use App\Models\FixtureMatch;
use App\Models\MatchSheet;
use App\Models\MatchSheetEvent;
use App\Models\MatchSheetSignature;
use App\Models\Player;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class MatchSheetController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();
        $query = MatchSheet::query()
            ->accessibleTo($user)
            ->with(['match.homeTeam', 'match.awayTeam', 'match.field', 'match.category', 'match.tournament']);

        if ($request->filled('search')) {
            $search = $request->string('search')->toString();
            $query->where(function ($query) use ($search) {
                $query
                    ->where('referee_name', 'like', "%{$search}%")
                    ->orWhere('assistant_name', 'like', "%{$search}%")
                    ->orWhere('responsible_name', 'like', "%{$search}%")
                    ->orWhere('status', 'like', "%{$search}%")
                    ->orWhereHas('match.homeTeam', fn ($query) => $query->where('name', 'like', "%{$search}%"))
                    ->orWhereHas('match.awayTeam', fn ($query) => $query->where('name', 'like', "%{$search}%"))
                    ->orWhereHas('match.category', fn ($query) => $query->where('name', 'like', "%{$search}%"))
                    ->orWhereHas('match.field', fn ($query) => $query->where('name', 'like', "%{$search}%"));
            });
        }

        if ($request->filled('status') && $request->string('status')->toString() !== 'all') {
            $query->where('status', $request->string('status')->toString());
        }

        if ($request->filled('tournament_id')) {
            $tournamentId = $request->integer('tournament_id');
            abort_unless($user?->canAccessTournament($tournamentId), 403, 'Este torneo no está dentro de tu alcance.');
            $query->whereHas('match', fn ($query) => $query->where('tournament_id', $tournamentId));
        }

        $sheets = $query->latest()->get();
        $statsQuery = MatchSheet::query()->accessibleTo($user);

        return view('admin.sheets.index', [
            'sheets' => $sheets,
            'filters' => [
                'search' => $request->string('search')->toString(),
                'status' => $request->string('status', 'all')->toString(),
                'tournament_id' => $request->string('tournament_id')->toString(),
            ],
            'accessibleTournaments' => $user->accessibleTournaments(),
            'subheading' => $user->canAccessAllTournaments()
                ? 'Carga oficial de partido, eventos, incidencias y cierre.'
                : 'Planillas de tus torneos asignados.',
            'stats' => [
                [(clone $statsQuery)->count(), 'Planillas', 'blue'],
                [(clone $statsQuery)->where('status', 'closed')->count(), 'Completas', 'green'],
                [(clone $statsQuery)->whereIn('status', ['draft', 'loading'])->count(), 'Pendientes', 'orange'],
                [(clone $statsQuery)->where('status', 'observed')->count(), 'Observadas', 'red'],
            ],
        ]);
    }

    public function create(): View
    {
        $matches = FixtureMatch::query()
            ->accessibleTo(request()->user())
            ->with(['homeTeam', 'awayTeam', 'category', 'field'])
            ->whereDoesntHave('sheet')
            ->orderBy('scheduled_at')
            ->get();

        return view('admin.sheets.create', compact('matches'));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'match_id' => ['required', 'exists:matches,id'],
        ]);

        $match = FixtureMatch::with(['homeTeam', 'awayTeam'])->findOrFail($data['match_id']);
        abort_unless(
            $request->user()?->canAccessTournament((int) $match->tournament_id),
            403,
            'Este partido no está dentro de tu alcance.'
        );

        if ($match->sheet) {
            return redirect()->route('admin.sheets.datos', $match->sheet);
        }

        $sheet = MatchSheet::create([
            'match_id' => $match->id,
            'status' => 'draft',
            'validation_status' => 'pending',
            'current_step' => 1,
            'referee_name' => $match->referee_name,
            'responsible_name' => $match->referee_name ? 'Árbitro '.$match->referee_name : 'Mesa 2',
        ]);

        $sheet->ensureSignatures();
        $this->audit($sheet, 'create', 'Planilla creada: '.$match->title());

        return redirect()
            ->route('admin.sheets.datos', $sheet)
            ->with('status', 'Planilla creada. Completá los datos del partido.');
    }

    public function show(MatchSheet $sheet): RedirectResponse
    {
        return redirect()->to($sheet->openRoute());
    }

    public function datos(MatchSheet $sheet): View
    {
        $this->assertSheetAccess($sheet);
        $sheet->load(['match.tournament', 'match.category', 'match.field', 'match.homeTeam', 'match.awayTeam']);
        $fields = Field::query()->accessibleTo(request()->user())->with('venue')->orderBy('name')->get();

        return $this->wizard($sheet, 1, [
            'fields' => $fields,
        ]);
    }

    public function updateDatos(Request $request, MatchSheet $sheet): RedirectResponse
    {
        $this->assertSheetAccess($sheet);
        $this->guardLocked($sheet);

        $data = $request->validate([
            'referee_name' => ['nullable', 'string', 'max:120'],
            'assistant_name' => ['nullable', 'string', 'max:120'],
            'field_id' => ['nullable', 'exists:fields,id'],
            'scheduled_at' => ['nullable', 'date'],
        ]);

        $sheet->update([
            'referee_name' => $data['referee_name'] ?? $sheet->referee_name,
            'assistant_name' => $data['assistant_name'] ?? $sheet->assistant_name,
            'responsible_name' => ($data['referee_name'] ?? null)
                ? 'Árbitro '.$data['referee_name']
                : $sheet->responsible_name,
            'current_step' => max((int) $sheet->current_step, 1),
        ]);

        $matchPayload = [];
        if (! empty($data['field_id'])) {
            $matchPayload['field_id'] = $data['field_id'];
        }
        if (! empty($data['scheduled_at'])) {
            $matchPayload['scheduled_at'] = $data['scheduled_at'];
        }
        if (array_key_exists('referee_name', $data)) {
            $matchPayload['referee_name'] = $data['referee_name'];
        }
        if ($matchPayload) {
            $sheet->match?->update($matchPayload);
        }

        $sheet->ensureSignatures();
        $this->audit($sheet, 'update', 'Datos de planilla actualizados: '.$sheet->match?->title());

        if ($request->boolean('continue')) {
            $sheet->update(['current_step' => max((int) $sheet->current_step, 2)]);

            return redirect()
                ->route('admin.sheets.eventos', $sheet)
                ->with('status', 'Datos guardados. Continuá con los eventos.');
        }

        return back()->with('status', 'Datos del partido guardados.');
    }

    public function eventos(MatchSheet $sheet): View
    {
        $this->assertSheetAccess($sheet);
        $sheet->load([
            'events.player.documents',
            'events.team.delegation',
            'match.homeTeam.players',
            'match.awayTeam.players',
        ]);

        $homePlayers = $sheet->match?->homeTeam?->players ?? collect();
        $awayPlayers = $sheet->match?->awayTeam?->players ?? collect();
        $players = $homePlayers
            ->concat($awayPlayers)
            ->sortBy(fn (Player $player) => $player->fullName())
            ->values();

        return $this->wizard($sheet, 2, [
            'players' => $players,
            'eventTypes' => MatchSheetEvent::typeLabels(),
        ]);
    }

    public function storeEvento(Request $request, MatchSheet $sheet): RedirectResponse
    {
        $this->assertSheetAccess($sheet);
        $this->guardLocked($sheet);

        $data = $request->validate([
            'type' => ['required', 'string', 'in:'.implode(',', array_keys(MatchSheetEvent::typeLabels()))],
            'player_id' => ['nullable', 'exists:players,id'],
            'minute' => ['nullable', 'integer', 'min:0', 'max:130'],
            'detail' => ['nullable', 'string', 'max:255'],
        ]);

        $player = ! empty($data['player_id']) ? Player::find($data['player_id']) : null;

        $sheet->events()->create([
            'type' => $data['type'],
            'player_id' => $player?->id,
            'team_id' => $player?->team_id,
            'minute' => $data['minute'] ?? null,
            'detail' => $data['detail'] ?? null,
        ]);

        $sheet->load(['events', 'match']);
        $sheet->recalcScore();
        $sheet->update([
            'status' => $sheet->status === 'draft' ? 'loading' : $sheet->status,
            'current_step' => max((int) $sheet->current_step, 2),
        ]);

        $this->audit($sheet, 'event', 'Evento '.$data['type'].' en '.$sheet->match?->title());

        return back()->with('status', 'Evento agregado a la planilla.');
    }

    public function destroyEvento(MatchSheet $sheet, MatchSheetEvent $event): RedirectResponse
    {
        $this->assertSheetAccess($sheet);
        $this->guardLocked($sheet);

        abort_unless($event->match_sheet_id === $sheet->id, 404);
        $event->delete();
        $sheet->load(['events', 'match']);
        $sheet->recalcScore();

        return back()->with('status', 'Evento anulado.');
    }

    public function incidencias(MatchSheet $sheet): View
    {
        $this->assertSheetAccess($sheet);
        $sheet->load(['incidents', 'signatures', 'match.homeTeam', 'match.awayTeam']);
        $sheet->ensureSignatures();

        return $this->wizard($sheet, 3);
    }

    public function storeIncidencia(Request $request, MatchSheet $sheet): RedirectResponse
    {
        $this->assertSheetAccess($sheet);
        $this->guardLocked($sheet);

        $data = $request->validate([
            'incident_title' => ['nullable', 'string', 'max:255'],
            'incident_team_id' => ['nullable', 'exists:teams,id'],
            'incident_moment' => ['nullable', 'string', 'max:120'],
            'incident_notes' => ['nullable', 'string', 'max:1200'],
            'fair_play_kind' => ['nullable', 'string', 'max:40'],
        ]);

        $sheet->update($data + ['current_step' => max((int) $sheet->current_step, 3)]);

        if (! empty($data['incident_title'])) {
            $teamName = $sheet->match?->homeTeam?->id === (int) ($data['incident_team_id'] ?? 0)
                ? $sheet->match->homeTeam->name
                : ($sheet->match?->awayTeam?->id === (int) ($data['incident_team_id'] ?? 0) ? $sheet->match->awayTeam->name : null);

            $sheet->incidents()->updateOrCreate(
                ['title' => $data['incident_title']],
                [
                    'type' => 'Fair Play',
                    'related_name' => $teamName,
                    'status' => 'applied',
                    'fair_play_kind' => filled($data['fair_play_kind'] ?? null) ? $data['fair_play_kind'] : null,
                    'notes' => $data['incident_notes'] ?? null,
                ]
            );
        }

        $this->audit($sheet, 'update', 'Incidencias actualizadas: '.$sheet->match?->title());

        if ($request->boolean('continue')) {
            $sheet->update(['current_step' => max((int) $sheet->current_step, 4)]);

            return redirect()
                ->route('admin.sheets.cierre', $sheet)
                ->with('status', 'Incidencias guardadas. Revisá el cierre.');
        }

        return back()->with('status', 'Incidencias y observaciones guardadas.');
    }

    public function sign(MatchSheet $sheet, MatchSheetSignature $signature): RedirectResponse
    {
        $this->assertSheetAccess($sheet);
        $this->guardLocked($sheet);
        abort_unless($signature->match_sheet_id === $sheet->id, 404);

        $signature->update([
            'signed' => true,
            'signed_at' => now(),
        ]);

        $this->audit($sheet, 'sign', 'Firma registrada: '.$signature->name);

        return back()->with('status', 'Firma registrada.');
    }

    public function cierre(MatchSheet $sheet): View
    {
        $this->assertSheetAccess($sheet);
        $sheet->load(['events', 'incidents', 'signatures', 'match.homeTeam', 'match.awayTeam', 'match.category', 'match.field']);
        $sheet->ensureSignatures();

        return $this->wizard($sheet, 4);
    }

    public function publish(MatchSheet $sheet): RedirectResponse
    {
        $this->assertSheetAccess($sheet);
        $this->guardLocked($sheet);
        $sheet->load(['events', 'match']);
        $sheet->recalcScore();
        $sheet->refresh();

        $sheet->update([
            'status' => 'closed',
            'validation_status' => 'validated',
            'locked' => true,
            'published' => true,
            'published_at' => now(),
            'current_step' => 4,
        ]);

        $sheet->match?->update([
            'home_score' => $sheet->home_score,
            'away_score' => $sheet->away_score,
            'status' => 'validated',
            'published' => true,
            'published_at' => now(),
            'referee_name' => $sheet->referee_name ?: $sheet->match->referee_name,
        ]);

        $this->audit($sheet, 'publish', 'Resultado publicado: '.$sheet->scoreTitle());

        return redirect()
            ->route('admin.sheets.cierre', $sheet)
            ->with('status', 'Resultado publicado y planilla cerrada.');
    }

    public function draft(MatchSheet $sheet): RedirectResponse
    {
        $this->assertSheetAccess($sheet);
        $this->guardLocked($sheet);

        $sheet->update([
            'status' => $sheet->events()->exists() ? 'loading' : 'draft',
            'validation_status' => 'pending',
            'current_step' => max((int) $sheet->current_step, 4),
        ]);

        $this->audit($sheet, 'draft', 'Borrador guardado: '.$sheet->match?->title());

        return back()->with('status', 'Borrador guardado. La planilla sigue editable.');
    }

    public function observe(MatchSheet $sheet): RedirectResponse
    {
        $this->assertSheetAccess($sheet);
        $sheet->update([
            'status' => 'observed',
            'validation_status' => 'review',
            'locked' => false,
        ]);

        $this->audit($sheet, 'observe', 'Planilla observada: '.$sheet->match?->title());

        return back()->with('status', 'Planilla marcada como observada.');
    }

    public function placa(MatchSheet $sheet): View
    {
        $this->assertSheetAccess($sheet);
        $sheet->load(['match.homeTeam', 'match.awayTeam', 'match.category', 'match.field']);

        return view('admin.sheets.placa', compact('sheet'));
    }

    public function pdf(MatchSheet $sheet): View
    {
        $this->assertSheetAccess($sheet);
        $sheet->load([
            'match.homeTeam.players',
            'match.awayTeam.players',
            'match.category',
            'match.field.venue',
            'match.tournament',
            'events.player',
            'events.team',
            'incidents',
            'signatures',
        ]);

        return view('admin.sheets.pdf', compact('sheet'));
    }

    /**
     * @param  array<string, mixed>  $extra
     */
    private function wizard(MatchSheet $sheet, int $step, array $extra = []): View
    {
        $titles = [
            1 => ['Planilla · Datos Partido', 'Paso 1 de 4: identificar partido, cancha, responsables y equipos.'],
            2 => ['Planilla · Eventos', 'Paso 2 de 4: goles, asistencias, tarjetas, cambios e incidencias.'],
            3 => ['Planilla · Incidencias y Firmas', 'Paso 3 de 4: observaciones disciplinarias y firmas responsables.'],
            4 => ['Planilla · Revisión y Cierre', 'Paso 4 de 4: validación final, publicación y bloqueo de edición.'],
        ];

        return view('admin.sheets.wizard', array_merge([
            'sheet' => $sheet,
            'step' => $step,
            'title' => $titles[$step][0],
            'subtitle' => $titles[$step][1],
        ], $extra));
    }

    private function guardLocked(MatchSheet $sheet): void
    {
        abort_if($sheet->locked, 403, 'La planilla ya está cerrada.');
    }

    private function assertSheetAccess(MatchSheet $sheet): void
    {
        $sheet->loadMissing('match');
        abort_unless(
            auth()->user()?->canAccessTournament((int) $sheet->match?->tournament_id),
            403,
            'Esta planilla no está dentro de tu alcance.'
        );
    }

    private function audit(MatchSheet $sheet, string $action, string $description): void
    {
        AuditLog::create([
            'user_id' => auth()->id(),
            'module' => 'Planillas',
            'action' => $action,
            'description' => $description,
            'auditable_type' => MatchSheet::class,
            'auditable_id' => $sheet->id,
            'metadata' => [
                'match' => $sheet->match?->title(),
                'status' => $sheet->status,
            ],
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ]);
    }
}
