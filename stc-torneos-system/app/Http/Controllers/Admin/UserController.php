<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Delegation;
use App\Models\Invitation;
use App\Models\Role;
use App\Models\Tournament;
use App\Models\User;
use App\Support\QrCode;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class UserController extends Controller
{
    public function index(Request $request): View
    {
        $query = User::query()->with(['roles', 'tournament']);

        if ($request->filled('search')) {
            $search = $request->string('search')->toString();
            $query->where(function ($query) use ($search) {
                $query
                    ->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%")
                    ->orWhere('current_scope', 'like', "%{$search}%")
                    ->orWhereHas('roles', fn ($query) => $query->where('name', 'like', "%{$search}%"));
            });
        }

        if ($request->filled('status') && $request->string('status')->toString() !== 'all') {
            $query->where('status', $request->string('status')->toString());
        }

        if ($request->filled('role_id')) {
            $query->whereHas('roles', fn ($query) => $query->where('roles.id', $request->integer('role_id')));
        }

        if ($request->filled('tournament_id')) {
            $tournamentId = $request->integer('tournament_id');
            $query->where(function ($query) use ($tournamentId) {
                $query->where('tournament_id', $tournamentId)->orWhere('extra_tournament_id', $tournamentId);
            });
        }

        $users = $query->orderBy('name')->get();

        return view('admin.users.index', [
            'users' => $users,
            'roles' => Role::query()->orderBy('name')->get(),
            'tournaments' => Tournament::query()->orderBy('name')->get(),
            'filters' => [
                'search' => $request->string('search')->toString(),
                'status' => $request->string('status', 'all')->toString(),
                'role_id' => $request->string('role_id')->toString(),
                'tournament_id' => $request->string('tournament_id')->toString(),
            ],
            'stats' => [
                [User::query()->count(), 'Usuarios', 'blue'],
                [User::query()->whereHas('roles', fn ($query) => $query->whereIn('slug', ['super-admin', 'admin-torneo']))->count(), 'Admins', 'cyan'],
                [User::query()->whereHas('roles', fn ($query) => $query->where('slug', 'delegado'))->count(), 'Delegados', 'green'],
                [
                    User::query()->where('status', 'pending')->count()
                        + Invitation::query()
                            ->where('status', 'pending')
                            ->where(fn ($query) => $query->whereNull('kind')->orWhereNotIn('kind', ['guardian', 'player', 'roster']))
                            ->count(),
                    'Pendientes',
                    'orange',
                ],
            ],
        ]);
    }

    public function create(): View
    {
        return $this->form(new User(['status' => 'pending']));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validatedData($request);
        $plainPassword = Str::password(12);

        $user = User::create([
            ...$this->profilePayload($data),
            'password' => Hash::make($plainPassword),
            'email_verified_at' => $data['status'] === 'active' ? now() : null,
        ]);

        $this->syncRoles($user, $request);

        Invitation::create([
            'name' => $user->name,
            'email' => $user->email,
            'role_id' => $user->primaryRole()?->id,
            'status' => 'pending',
            'token' => Str::random(40),
            'scope_type' => $user->tournament_id ? 'tournament' : 'system',
            'scope_id' => $user->tournament_id,
            'invited_by' => auth()->id(),
            'expires_at' => now()->addDays(7),
        ]);

        $this->audit($user, 'create', 'Usuario creado: '.$user->name);

        return redirect()
            ->route('admin.users.show', $user)
            ->with('status', 'Usuario creado. Se generó la invitación para activar el acceso al Admin Web.');
    }

    public function show(User $user): View
    {
        $user->load(['roles.permissions', 'tournament', 'extraTournament', 'delegation']);

        return view('admin.users.show', [
            'user' => $user,
            'capabilities' => $user->capabilities(),
        ]);
    }

    public function credential(User $user): View
    {
        $user->load(['roles', 'tournament', 'delegation']);
        $payload = route('admin.users.credential', $user);
        $qrUrl = QrCode::url($payload);

        return view('admin.credentials.card', [
            'title' => $user->name,
            'kind' => $user->credentialKind(),
            'lines' => array_filter([
                $user->roleLabel(),
                $user->tournament?->name ?: $user->tournamentLabel(),
                $user->delegation?->name,
                $user->email,
            ]),
            'photoUrl' => $user->photoUrl(),
            'qrUrl' => $qrUrl,
            'backUrl' => route('admin.users.show', $user),
        ]);
    }

    public function edit(User $user): View
    {
        $user->load(['roles.permissions', 'tournament', 'extraTournament', 'delegation']);

        return $this->form($user);
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $data = $this->validatedData($request, $user);
        $user->update($this->profilePayload($data));
        $this->syncRoles($user, $request);
        $this->audit($user, 'update', 'Usuario actualizado: '.$user->name);

        return redirect()
            ->route('admin.users.show', $user)
            ->with('status', 'Usuario actualizado correctamente.');
    }

    public function updateStatus(Request $request, User $user): RedirectResponse
    {
        $this->guardProtected($user);

        $data = $request->validate([
            'status' => ['required', 'string', Rule::in(array_keys(User::statusLabels()))],
            'suspension_reason' => ['nullable', 'string', 'max:180'],
        ]);

        $user->update([
            'status' => $data['status'],
            'suspension_reason' => $data['status'] === 'suspended'
                ? ($data['suspension_reason'] ?? $user->suspension_reason)
                : null,
        ]);

        $this->audit($user, 'status_update', 'Estado de '.$user->name.' actualizado a '.$user->statusLabel());

        return back()->with('status', 'Estado de acceso actualizado.');
    }

    public function destroy(User $user): RedirectResponse
    {
        $this->guardProtected($user);
        $this->audit($user, 'delete', 'Usuario eliminado: '.$user->name);
        $user->roles()->detach();
        $user->delete();

        return redirect()->route('admin.users.index')->with('status', 'Usuario eliminado correctamente.');
    }

    private function form(User $user): View
    {
        $roles = Role::query()->orderBy('id')->get();

        return view('admin.users.form', [
            'user' => $user,
            'roles' => $roles,
            'tournaments' => Tournament::query()->orderBy('name')->get(),
            'delegations' => Delegation::query()->with('tournament')->orderBy('name')->get(),
            'method' => $user->exists ? 'PUT' : 'POST',
            'url' => $user->exists ? route('admin.users.update', $user) : route('admin.users.store'),
            'capabilities' => $user->exists ? $user->capabilities() : User::capabilitiesForSlugs(collect()),
            'rolePermissionMap' => $roles->mapWithKeys(function (Role $role) {
                $role->loadMissing('permissions');

                return [$role->id => $role->permissions->pluck('slug')->values()];
            }),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function validatedData(Request $request, ?User $user = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:180'],
            'email' => ['required', 'email', 'max:180', Rule::unique('users', 'email')->ignore($user?->id)],
            'phone' => ['nullable', 'string', 'max:40'],
            'role_id' => ['required', 'exists:roles,id'],
            'role_ids' => ['nullable', 'array'],
            'role_ids.*' => ['exists:roles,id'],
            'tournament_id' => ['nullable', 'exists:tournaments,id'],
            'extra_tournament_id' => ['nullable', 'exists:tournaments,id'],
            'delegation_id' => ['nullable', 'exists:delegations,id'],
            'status' => ['required', 'string', Rule::in(array_keys(User::statusLabels()))],
            'suspension_reason' => ['nullable', 'string', 'max:180'],
            'current_scope' => ['nullable', 'string', 'max:180'],
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function profilePayload(array $data): array
    {
        $tournament = ! empty($data['tournament_id']) ? Tournament::find($data['tournament_id']) : null;
        $roleSlug = Role::query()->whereKey($data['role_id'])->value('slug');

        return [
            'name' => $data['name'],
            'email' => $data['email'],
            'phone' => $data['phone'] ?? null,
            'status' => $data['status'],
            'tournament_id' => $data['tournament_id'] ?? null,
            'extra_tournament_id' => $data['extra_tournament_id'] ?? null,
            'delegation_id' => $data['delegation_id'] ?? null,
            'suspension_reason' => $data['status'] === 'suspended' ? ($data['suspension_reason'] ?? null) : null,
            'current_scope' => ($data['current_scope'] ?? null) ?: ($roleSlug === 'super-admin' ? 'Torneo completo' : $tournament?->name),
        ];
    }

    private function syncRoles(User $user, Request $request): void
    {
        $ids = collect([$request->integer('role_id')])
            ->merge($request->input('role_ids', []))
            ->filter()
            ->unique()
            ->values();

        $payload = [];
        foreach ($ids as $id) {
            $payload[(int) $id] = [
                'scope_type' => $request->filled('tournament_id') ? 'tournament' : 'system',
                'scope_id' => $request->integer('tournament_id') ?: null,
                'assigned_by' => auth()->id(),
                'assigned_at' => now(),
            ];
        }

        $user->roles()->sync($payload);
        $user->unsetRelation('roles');
    }

    private function guardProtected(User $user): void
    {
        abort_if($user->id === auth()->id(), 403, 'No podés modificar tu propio acceso desde acá.');
        abort_if($user->hasRole('super-admin') && User::query()->whereHas('roles', fn ($query) => $query->where('slug', 'super-admin'))->count() <= 1, 403, 'Tiene que quedar al menos un Super Admin.');
    }

    private function audit(User $user, string $action, string $description): void
    {
        AuditLog::create([
            'user_id' => auth()->id(),
            'module' => 'Usuarios',
            'action' => $action,
            'description' => $description,
            'auditable_type' => User::class,
            'auditable_id' => $user->id,
            'metadata' => [
                'email' => $user->email,
                'status' => $user->status,
                'roles' => $user->roles()->pluck('slug')->all(),
            ],
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ]);
    }
}
