<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AuditController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();
        $logs = $this->filtered($request)->with(['user.roles'])->latest('id')->limit(200)->get();
        $scoped = $this->scopedLogs($request);
        $usersQuery = User::query()->orderBy('name');
        if (! $user->canAccessAllTournaments() && $user->assignedTournamentIds()) {
            $ids = $user->assignedTournamentIds();
            $usersQuery->where(function ($query) use ($ids) {
                $query->whereIn('tournament_id', $ids)->orWhereIn('extra_tournament_id', $ids);
            });
        }

        return view('admin.audit.index', [
            'logs' => $logs,
            'users' => $usersQuery->get(),
            'modules' => (clone $scoped)->whereNotNull('module')->distinct()->orderBy('module')->pluck('module'),
            'filters' => [
                'search' => $request->string('search')->toString(),
                'module' => $request->string('module', 'all')->toString(),
                'user_id' => $request->string('user_id')->toString(),
            ],
            'subheading' => $user->canAccessAllTournaments()
                ? 'Consulta de trazabilidad: usuario, rol, acción, registro afectado y cambios.'
                : 'Historial de operación de tus torneos asignados.',
            'stats' => [
                [(clone $scoped)->count(), 'Eventos', 'blue'],
                [(clone $scoped)->whereNotNull('user_id')->distinct()->count('user_id'), 'Usuarios', 'cyan'],
                [(clone $scoped)->where(function ($query) {
                    $query
                        ->whereIn('module', ['Planillas', 'Resultados', 'Usuarios'])
                        ->orWhereIn('action', ['status_update', 'send', 'publish', 'observe']);
                })->count(), 'Alertas', 'yellow'],
                [(clone $scoped)->whereIn('action', ['delete', 'status_update', 'publish', 'send'])->count(), 'Críticos', 'red'],
            ],
        ]);
    }

    public function show(AuditLog $log): View
    {
        $this->assertLogAccess($log);
        $log->load(['user.roles']);

        $timelineQuery = AuditLog::query()->with('user')->orderBy('id')->limit(8);
        if ($log->auditable_type && $log->auditable_id) {
            $timelineQuery->where('auditable_type', $log->auditable_type)->where('auditable_id', $log->auditable_id);
        } else {
            $timelineQuery->where('module', $log->module)->where('id', '<=', $log->id);
        }

        return view('admin.audit.show', [
            'log' => $log,
            'timeline' => $timelineQuery->get(),
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        $logs = $this->filtered($request)->with('user')->latest('id')->get();

        return response()->streamDownload(function () use ($logs) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['Fecha', 'Usuario', 'Rol', 'Módulo', 'Acción', 'Registro', 'Descripción', 'IP']);

            foreach ($logs as $log) {
                fputcsv($handle, [
                    $log->created_at?->format('d/m/Y H:i'),
                    $log->user?->name ?? 'Sistema',
                    $log->user?->roleLabel() ?? 'Sin rol',
                    $log->module,
                    $log->actionLabel(),
                    $log->recordLabel(),
                    $log->description,
                    $log->ip_address,
                ]);
            }

            fclose($handle);
        }, 'auditoria-stc.csv');
    }

    public function download(AuditLog $log): StreamedResponse
    {
        $this->assertLogAccess($log);
        $log->load('user.roles');

        $payload = [
            'codigo' => $log->referenceCode(),
            'fecha' => $log->created_at?->format('d/m/Y H:i:s'),
            'usuario' => $log->user?->name ?? 'Sistema',
            'email' => $log->user?->email,
            'rol' => $log->user?->roleLabel() ?? 'Sin rol',
            'modulo' => $log->module,
            'accion' => $log->actionLabel(),
            'descripcion' => $log->description,
            'registro' => $log->recordLabel(),
            'valor_anterior' => $log->previousValue(),
            'valor_nuevo' => $log->newValue(),
            'motivo' => $log->reason(),
            'ip' => $log->ip_address,
            'user_agent' => $log->user_agent,
            'metadata' => $log->metadata,
        ];

        return response()->streamDownload(function () use ($payload) {
            echo json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        }, 'auditoria-'.$log->id.'.json', ['Content-Type' => 'application/json']);
    }

    public function review(Request $request, AuditLog $log): RedirectResponse
    {
        $this->assertLogAccess($log);

        $metadata = $log->metadata ?? [];
        $metadata['reviewed_at'] = now()->toIso8601String();
        $metadata['reviewed_by'] = $request->user()?->id;
        $log->forceFill(['metadata' => $metadata])->save();

        return back()->with('status', 'Registro marcado como revisado. El evento no se elimina.');
    }

    private function filtered(Request $request)
    {
        return $this->scopedLogs($request)
            ->when($request->filled('search'), function ($query) use ($request) {
                $search = $request->string('search')->toString();
                $query->where(function ($query) use ($search) {
                    $query
                        ->where('description', 'like', "%{$search}%")
                        ->orWhere('action', 'like', "%{$search}%")
                        ->orWhere('module', 'like', "%{$search}%")
                        ->orWhereHas('user', fn ($userQuery) => $userQuery->where('name', 'like', "%{$search}%"));
                });
            })
            ->when($request->filled('module') && $request->string('module')->toString() !== 'all', fn ($query) => $query->where('module', $request->string('module')->toString()))
            ->when($request->filled('user_id'), fn ($query) => $query->where('user_id', $request->integer('user_id')));
    }

    private function scopedLogs(Request $request)
    {
        $user = $request->user();
        $query = AuditLog::query();

        if ($user && ! $user->canAccessAllTournaments() && $user->assignedTournamentIds()) {
            $ids = $user->assignedTournamentIds();
            $query->where(function ($query) use ($ids) {
                $query
                    ->whereHas('user', function ($userQuery) use ($ids) {
                        $userQuery->whereIn('tournament_id', $ids)->orWhereIn('extra_tournament_id', $ids);
                    })
                    ->orWhereNull('user_id');
            });
        }

        return $query;
    }

    private function assertLogAccess(AuditLog $log): void
    {
        $user = auth()->user();
        $log->loadMissing('user');
        if ($user?->canAccessAllTournaments() || ! $user?->assignedTournamentIds()) {
            return;
        }

        $ids = $user->assignedTournamentIds();
        $ownerIds = array_filter([(int) $log->user?->tournament_id, (int) $log->user?->extra_tournament_id]);
        abort_unless(
            $log->user_id === null || array_intersect($ownerIds, $ids) !== [],
            403,
            'Este evento no está dentro de tu alcance.'
        );
    }
}
