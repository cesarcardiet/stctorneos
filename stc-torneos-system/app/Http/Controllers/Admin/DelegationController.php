<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Delegation;
use App\Support\AdminContext;
use App\Support\QrCode;
use App\Support\ShieldPayload;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Illuminate\View\View;

class DelegationController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();
        $accessibleTournaments = $user->accessibleTournaments();

        $filters = AdminContext::applyTournamentFilter($request, [
            'search' => $request->string('search')->toString(),
            'status' => $request->string('status', 'all')->toString(),
            'tournament_id' => $request->string('tournament_id')->toString(),
        ]);

        $allDelegations = Delegation::query()
            ->accessibleTo($user)
            ->withCount(['teams', 'players'])
            ->get();

        $delegations = Delegation::query()
            ->accessibleTo($user)
            ->with(['tournament', 'teams.category'])
            ->withCount(['teams', 'players'])
            ->when($filters['search'] !== '', function ($query) use ($filters) {
                $search = $filters['search'];
                $query->where(fn ($query) => $query
                    ->where('name', 'like', "%{$search}%")
                    ->orWhere('delegate_name', 'like', "%{$search}%")
                    ->orWhere('city', 'like', "%{$search}%")
                    ->orWhere('country', 'like', "%{$search}%"));
            })
            ->when($filters['status'] !== '' && $filters['status'] !== 'all', fn ($query) => $query->where('status', $filters['status']))
            ->when($filters['tournament_id'] !== '', function ($query) use ($filters, $user) {
                $tournamentId = (int) $filters['tournament_id'];
                abort_unless($user->canAccessTournament($tournamentId), 403, 'Este torneo no está dentro de tu alcance.');
                $query->where('tournament_id', $tournamentId);
            })
            ->latest()
            ->get();

        $stats = [
            ['Delegaciones', $allDelegations->count(), 'blue'],
            ['Aprobadas', $allDelegations->where('status', 'approved')->count(), 'green'],
            ['Pendientes', $allDelegations->where('status', 'pending')->count(), 'yellow'],
            ['Observadas', $allDelegations->where('status', 'observed')->count(), 'red'],
        ];

        $subheading = 'Clubes, delegados, estados de inscripción y cupos.';

        return view('admin.delegations.index', compact(
            'delegations',
            'stats',
            'filters',
            'accessibleTournaments',
            'subheading',
        ));
    }

    public function create(): View
    {
        return view('admin.delegations.form', [
            'delegation' => new Delegation(['country' => 'Argentina', 'status' => 'pending', 'logo_path' => 'images/stc-logo.png']),
            'method' => 'POST',
            'title' => 'Crear delegación',
            'tournaments' => auth()->user()->accessibleTournaments(),
            'url' => route('admin.delegations.store'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $delegation = Delegation::create($this->validatedData($request));
        $delegation->teams()->update(['shield_path' => $delegation->logo_path]);
        $this->audit('create', $delegation, 'Delegación creada desde Admin Web.');

        return redirect()->route('admin.delegations.show', $delegation)->with('status', 'Delegación creada correctamente.');
    }

    public function show(Delegation $delegation): View
    {
        $this->assertDelegationAccess($delegation);

        $delegation
            ->load(['tournament', 'teams' => fn ($query) => $query->with('category')->withCount('players')])
            ->loadCount(['teams', 'players']);

        $inscription = $delegation->inscriptionStats();

        return view('admin.delegations.show', compact('delegation', 'inscription'));
    }

    public function credential(Delegation $delegation): View
    {
        $this->assertDelegationAccess($delegation);
        $delegation->load(['tournament', 'delegates.roles']);
        $delegate = $delegation->principalDelegate();
        $payload = route('admin.delegations.credential', $delegation);
        $qrUrl = QrCode::url($payload);

        return view('admin.credentials.card', [
            'title' => $delegate?->name ?: ($delegation->delegate_name ?: $delegation->name),
            'kind' => 'Delegado',
            'lines' => array_filter([
                $delegation->name,
                $delegation->tournament?->name,
                $delegate?->email ?: $delegation->delegate_email,
                $delegation->delegate_phone,
            ]),
            'photoUrl' => $delegation->logoUrl() ?: asset('images/stc-logo.png'),
            'qrUrl' => $qrUrl,
            'backUrl' => route('admin.delegations.show', $delegation),
        ]);
    }

    public function edit(Delegation $delegation): View
    {
        $this->assertDelegationAccess($delegation);
        $delegation->load(['teams.category']);

        return view('admin.delegations.form', [
            'delegation' => $delegation,
            'method' => 'PUT',
            'title' => 'Editar delegación',
            'tournaments' => auth()->user()->accessibleTournaments(),
            'url' => route('admin.delegations.update', $delegation),
        ]);
    }

    public function update(Request $request, Delegation $delegation): RedirectResponse
    {
        $this->assertDelegationAccess($delegation);
        $oldLogo = $delegation->logo_path;
        $delegation->update($this->validatedData($request));
        if ($delegation->wasChanged('logo_path')) {
            $delegation->teams()->update(['shield_path' => $delegation->logo_path]);
            Delegation::deleteUnusedPath($oldLogo);
        }
        $this->audit('update', $delegation, 'Delegación actualizada desde Admin Web.');

        return redirect()->route('admin.delegations.show', $delegation)->with('status', 'Delegación actualizada correctamente.');
    }

    public function updateStatus(Request $request, Delegation $delegation): RedirectResponse
    {
        $this->assertDelegationAccess($delegation);
        $data = $request->validate(['status' => ['required', 'string', 'in:approved,pending,observed,blocked']]);
        $delegation->update($data);
        $this->audit('status_update', $delegation, 'Estado de delegación actualizado.');

        return back()->with('status', 'Estado de la delegación actualizado.');
    }

    public function destroy(Delegation $delegation): RedirectResponse
    {
        $this->assertDelegationAccess($delegation);
        $this->audit('delete', $delegation, 'Delegación eliminada desde Admin Web.');
        $delegation->teams()->delete();
        $delegation->delete();

        return redirect()->route('admin.delegations.index')->with('status', 'Delegación eliminada correctamente.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validatedData(Request $request): array
    {
        $data = $request->validate([
            'tournament_id' => ['required', 'exists:tournaments,id'],
            'name' => ['required', 'string', 'max:180'],
            'country' => ['required', 'string', 'max:80'],
            'city' => ['nullable', 'string', 'max:120'],
            'logo_path' => ['nullable', 'string', 'max:500'],
            'logo_file' => ['nullable', 'file', 'max:8192'],
            'shield_data' => ['nullable', 'string', 'max:8000000'],
            'delegate_name' => ['required', 'string', 'max:160'],
            'delegate_email' => ['nullable', 'email', 'max:180'],
            'delegate_phone' => ['nullable', 'string', 'max:80'],
            'additional_contacts' => ['nullable', 'array', 'max:3'],
            'additional_contacts.*.name' => ['nullable', 'string', 'max:160'],
            'additional_contacts.*.role' => ['nullable', 'string', 'max:120'],
            'additional_contacts.*.email' => ['nullable', 'email', 'max:180'],
            'additional_contacts.*.phone' => ['nullable', 'string', 'max:80'],
            'status' => ['required', 'string', 'in:approved,pending,observed,blocked'],
            'notes' => ['nullable', 'string', 'max:1200'],
        ]);

        abort_unless(
            $request->user()->canAccessTournament((int) $data['tournament_id']),
            403,
            'Este torneo no está dentro de tu alcance.'
        );

        unset($data['logo_file'], $data['shield_data']);

        $data['additional_contacts'] = collect($data['additional_contacts'] ?? [])
            ->map(fn ($row) => [
                'name' => trim((string) ($row['name'] ?? '')),
                'role' => trim((string) ($row['role'] ?? '')),
                'email' => trim((string) ($row['email'] ?? '')),
                'phone' => trim((string) ($row['phone'] ?? '')),
            ])
            ->filter(fn ($row) => $row['name'] !== '')
            ->values()
            ->all();

        $payload = ShieldPayload::fromRequest($request, 'logo_file');
        if ($payload) {
            $directory = public_path('images/delegations');
            $filename = Str::slug($data['name'] ?? 'club').'-'.Str::random(8).'.'.$payload[1];
            File::ensureDirectoryExists($directory);
            File::put($directory.DIRECTORY_SEPARATOR.$filename, $payload[0]);
            $data['logo_path'] = 'images/delegations/'.$filename;
        } elseif ($request->hasFile('logo_file')) {
            $file = $request->file('logo_file');
            $directory = public_path('images/delegations');
            $filename = Str::slug(pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME))
                .'-'.Str::random(8).'.'.$file->getClientOriginalExtension();

            File::ensureDirectoryExists($directory);
            $file->move($directory, $filename);

            $data['logo_path'] = 'images/delegations/'.$filename;
        }

        return $data;
    }

    private function assertDelegationAccess(Delegation $delegation): void
    {
        abort_unless(
            Delegation::query()->accessibleTo(auth()->user())->whereKey($delegation->id)->exists(),
            403,
            'Esta delegación no está dentro de tu alcance.'
        );
    }

    private function audit(string $action, Delegation $delegation, string $description): void
    {
        AuditLog::create([
            'user_id' => auth()->id(),
            'module' => 'Delegaciones',
            'action' => $action,
            'description' => $description,
            'auditable_type' => Delegation::class,
            'auditable_id' => $delegation->id,
            'metadata' => ['name' => $delegation->name],
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'created_at' => now(),
        ]);
    }
}
