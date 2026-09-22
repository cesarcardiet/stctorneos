<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Permission;
use App\Models\Role;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class RoleController extends Controller
{
    public function index(): View
    {
        $roles = Role::query()
            ->withCount('users', 'permissions')
            ->orderBy('name')
            ->get();

        return view('admin.roles.index', [
            'roles' => $roles,
            'subheading' => 'Permisos del sistema STC. Solo Super Admin puede editarlos.',
        ]);
    }

    public function edit(Role $role): View
    {
        abort_if($role->slug === 'super-admin', 403, 'El rol Super Admin no se edita desde acá.');

        $role->load('permissions');
        $permissions = Permission::query()->orderBy('group')->orderBy('name')->get()->groupBy('group');

        return view('admin.roles.edit', [
            'role' => $role,
            'permissions' => $permissions,
            'assigned' => $role->permissions->pluck('id')->all(),
        ]);
    }

    public function update(Request $request, Role $role): RedirectResponse
    {
        abort_if($role->slug === 'super-admin', 403, 'El rol Super Admin no se edita desde acá.');

        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:500'],
            'color' => ['nullable', 'string', 'max:20'],
            'permission_ids' => ['nullable', 'array'],
            'permission_ids.*' => ['integer', 'exists:permissions,id'],
        ]);

        $role->update([
            'name' => $data['name'],
            'description' => $data['description'] ?? '',
            'color' => $data['color'] ?: $role->color,
        ]);

        $permissionIds = collect($data['permission_ids'] ?? [])
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();

        $role->permissions()->sync($permissionIds);

        AuditLog::create([
            'user_id' => $request->user()?->id,
            'module' => 'Roles',
            'action' => 'update',
            'description' => 'Permisos del rol '.$role->name.' actualizados.',
            'metadata' => ['role_id' => $role->id, 'permissions' => $permissionIds],
        ]);

        return redirect()
            ->route('admin.roles.index')
            ->with('status', 'Rol '.$role->name.' actualizado.');
    }
}
