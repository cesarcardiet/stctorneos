<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AppNotification;
use App\Services\NotificationDispatchService;
use App\Models\AuditLog;
use App\Models\Category;
use App\Models\ContentPost;
use App\Models\FavoriteMatch;
use App\Models\FavoriteTeam;
use App\Models\FixtureMatch;
use App\Models\MatchSheet;
use App\Models\MediaAsset;
use App\Models\Player;
use App\Services\PlaqueFactory;
use App\Support\TextSearch;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Illuminate\View\View;

class CommunicationController extends Controller
{
    public function index(Request $request): View
    {
        $posts = ContentPost::query()
            ->accessibleTo($request->user())
            ->with(['tournament', 'author'])
            ->when($request->filled('search'), function ($query) use ($request) {
                $search = $request->string('search')->toString();
                $query->where(function ($query) use ($search) {
                    $query
                        ->where('title', 'like', "%{$search}%")
                        ->orWhere('summary', 'like', "%{$search}%")
                        ->orWhere('type', 'like', "%{$search}%");
                });
            })
            ->when($request->filled('type') && $request->string('type')->toString() !== 'all', fn ($query) => $query->where('type', $request->string('type')->toString()))
            ->latest()
            ->get();

        $all = ContentPost::query()->accessibleTo($request->user())->get();

        return view('admin.communications.index', [
            'tab' => 'content',
            'posts' => $posts,
            'filters' => [
                'search' => $request->string('search')->toString(),
                'type' => $request->string('type', 'all')->toString(),
            ],
            'subheading' => $request->user()->canAccessAllTournaments()
                ? 'Noticias, comunicados oficiales y contenido que alimenta la app.'
                : 'Contenido de tus torneos asignados.',
            'stats' => [
                [$all->count(), 'Piezas', 'blue'],
                [$all->where('status', 'published')->count(), 'Publicadas', 'green'],
                [$all->where('status', 'scheduled')->count(), 'Programadas', 'orange'],
                [$all->where('status', 'draft')->count(), 'Borradores', 'yellow'],
            ],
        ]);
    }

    public function create(): View
    {
        return $this->form(new ContentPost([
            'type' => 'news',
            'audience' => 'public',
            'status' => 'draft',
        ]));
    }

    public function store(Request $request): RedirectResponse
    {
        $post = ContentPost::create($this->postPayload($request));

        $this->storeCover($request, $post);
        $this->audit($post, 'create', 'Contenido creado: '.$post->title);

        return redirect()
            ->route('admin.communications.show', $post)
            ->with('status', 'Contenido guardado. Podés publicarlo cuando esté listo.');
    }

    public function show(ContentPost $post): View
    {
        $this->assertPostAccess($post);
        $post->load(['tournament', 'category', 'author', 'media']);

        return view('admin.communications.show', compact('post'));
    }

    public function edit(ContentPost $post): View
    {
        $this->assertPostAccess($post);

        return $this->form($post);
    }

    public function update(Request $request, ContentPost $post): RedirectResponse
    {
        $this->assertPostAccess($post);
        $post->update($this->postPayload($request, $post));
        $this->storeCover($request, $post);
        $this->audit($post, 'update', 'Contenido actualizado: '.$post->title);

        return redirect()
            ->route('admin.communications.show', $post)
            ->with('status', 'Contenido actualizado.');
    }

    public function updateStatus(Request $request, ContentPost $post): RedirectResponse
    {
        $this->assertPostAccess($post);
        $data = $request->validate([
            'status' => ['required', 'string', 'in:draft,scheduled,published,archived'],
        ]);

        $post->update([
            'status' => $data['status'],
            'published_at' => $data['status'] === 'published' ? ($post->published_at ?: now()) : $post->published_at,
        ]);

        $this->audit($post, 'status_update', 'Estado de contenido: '.$post->statusLabel());

        return back()->with('status', 'Estado actualizado a '.$post->statusLabel().'.');
    }

    public function destroy(ContentPost $post): RedirectResponse
    {
        $this->assertPostAccess($post);
        $this->audit($post, 'delete', 'Contenido eliminado: '.$post->title);
        $post->delete();

        return redirect()
            ->route('admin.communications.index')
            ->with('status', 'Contenido eliminado.');
    }

    public function notifications(Request $request): View
    {
        $items = AppNotification::query()
            ->accessibleTo($request->user())
            ->with(['author', 'tournament'])
            ->when($request->filled('search'), function ($query) use ($request) {
                $search = $request->string('search')->toString();
                $query->where(function ($query) use ($search) {
                    $query
                        ->where('title', 'like', "%{$search}%")
                        ->orWhere('body', 'like', "%{$search}%")
                        ->orWhere('audience', 'like', "%{$search}%");
                });
            })
            ->latest()
            ->get();

        $all = AppNotification::query()->accessibleTo($request->user())->get();

        return view('admin.communications.notifications', [
            'tab' => 'notifications',
            'items' => $items,
            'stats' => [
                [$all->count(), 'Avisos', 'blue'],
                [$all->where('status', 'sent')->count(), 'Enviados', 'green'],
                [$all->where('status', 'scheduled')->count(), 'Programados', 'orange'],
                [$all->sum('recipients_count'), 'Destinatarios', 'cyan'],
            ],
        ]);
    }

    public function createNotification(): View
    {
        return view('admin.communications.notification-form', [
            'notification' => new AppNotification([
                'channel' => 'both',
                'audience' => 'all',
                'status' => 'draft',
            ]),
            'method' => 'POST',
            'url' => route('admin.communications.notifications.store'),
            'tournaments' => request()->user()->accessibleTournaments(),
            'posts' => ContentPost::query()->accessibleTo(request()->user())->latest()->get(),
        ]);
    }

    public function storeNotification(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:180'],
            'body' => ['nullable', 'string', 'max:2000'],
            'channel' => ['required', 'string', 'in:in_app,email,both,push'],
            'audience' => ['required', 'string', 'in:public,all,delegates,referees,staff'],
            'tournament_id' => ['nullable', 'exists:tournaments,id'],
            'content_post_id' => ['nullable', 'exists:content_posts,id'],
            'scheduled_at' => ['nullable', 'date'],
            'send_now' => ['nullable', 'boolean'],
        ]);

        abort_unless(
            ! ($data['tournament_id'] ?? null) || $request->user()?->canAccessTournament((int) $data['tournament_id']),
            403,
            'Este torneo no está dentro de tu alcance.'
        );

        $notification = AppNotification::create([
            'title' => $data['title'],
            'body' => $data['body'] ?? null,
            'channel' => $data['channel'],
            'audience' => $data['audience'],
            'tournament_id' => $data['tournament_id'] ?? null,
            'content_post_id' => $data['content_post_id'] ?? null,
            'created_by' => auth()->id(),
            'status' => $request->boolean('send_now') ? 'draft' : (($data['scheduled_at'] ?? null) ? 'scheduled' : 'draft'),
            'scheduled_at' => $data['scheduled_at'] ?? null,
        ]);

        $this->auditNotification($notification, 'create', 'Notificación creada: '.$notification->title);

        if ($request->boolean('send_now')) {
            return $this->sendNotification($notification);
        }

        return redirect()
            ->route('admin.communications.notifications.show', $notification)
            ->with('status', 'Notificación guardada.');
    }

    public function showNotification(AppNotification $appNotification): View
    {
        $this->assertNotificationAccess($appNotification);
        $appNotification->load(['author', 'tournament', 'post', 'recipients.user']);

        return view('admin.communications.notification-show', [
            'notification' => $appNotification,
        ]);
    }

    public function editNotification(AppNotification $appNotification): View
    {
        $this->assertNotificationAccess($appNotification);
        abort_unless(in_array($appNotification->status, ['draft', 'scheduled'], true), 422, 'Solo se editan borradores o avisos programados.');

        return view('admin.communications.notification-form', [
            'notification' => $appNotification,
            'method' => 'PUT',
            'url' => route('admin.communications.notifications.update', $appNotification),
            'tournaments' => request()->user()->accessibleTournaments(),
            'posts' => ContentPost::query()->accessibleTo(request()->user())->latest()->get(),
        ]);
    }

    public function updateNotification(Request $request, AppNotification $appNotification): RedirectResponse
    {
        $this->assertNotificationAccess($appNotification);
        abort_unless(in_array($appNotification->status, ['draft', 'scheduled'], true), 422, 'Solo se editan borradores o avisos programados.');

        $data = $request->validate([
            'title' => ['required', 'string', 'max:180'],
            'body' => ['nullable', 'string', 'max:2000'],
            'channel' => ['required', 'string', 'in:in_app,email,both,push'],
            'audience' => ['required', 'string', 'in:public,all,delegates,referees,staff'],
            'tournament_id' => ['nullable', 'exists:tournaments,id'],
            'content_post_id' => ['nullable', 'exists:content_posts,id'],
            'scheduled_at' => ['nullable', 'date'],
            'send_now' => ['nullable', 'boolean'],
        ]);

        abort_unless(
            ! ($data['tournament_id'] ?? null) || $request->user()?->canAccessTournament((int) $data['tournament_id']),
            403,
            'Este torneo no está dentro de tu alcance.'
        );

        $appNotification->update([
            'title' => $data['title'],
            'body' => $data['body'] ?? null,
            'channel' => $data['channel'],
            'audience' => $data['audience'],
            'tournament_id' => $data['tournament_id'] ?? null,
            'content_post_id' => $data['content_post_id'] ?? null,
            'status' => $request->boolean('send_now') ? 'draft' : (($data['scheduled_at'] ?? null) ? 'scheduled' : 'draft'),
            'scheduled_at' => $data['scheduled_at'] ?? null,
        ]);

        $this->auditNotification($appNotification, 'update', 'Notificación actualizada: '.$appNotification->title);

        if ($request->boolean('send_now')) {
            return $this->sendNotification($appNotification);
        }

        return redirect()
            ->route('admin.communications.notifications.show', $appNotification)
            ->with('status', 'Notificación actualizada.');
    }

    public function destroyNotification(AppNotification $appNotification): RedirectResponse
    {
        $this->assertNotificationAccess($appNotification);
        abort_unless(in_array($appNotification->status, ['draft', 'scheduled'], true), 422, 'Solo se borra un aviso que todavía no se envió.');

        $this->auditNotification($appNotification, 'delete', 'Notificación eliminada: '.$appNotification->title);
        $appNotification->delete();

        return redirect()
            ->route('admin.communications.notifications')
            ->with('status', 'Notificación eliminada.');
    }

    public function sendNotification(AppNotification $appNotification): RedirectResponse
    {
        $this->assertNotificationAccess($appNotification);

        $recipientCount = app(NotificationDispatchService::class)->dispatch(
            $appNotification,
            auth()->user()
        );

        $this->auditNotification($appNotification, 'send', 'Notificación enviada: '.$appNotification->title.' ('.$recipientCount.' destinatarios)');

        $message = match (true) {
            $appNotification->audience === 'public' => 'Aviso publicado para la app pública.',
            in_array($appNotification->channel, ['email', 'both'], true) => 'Notificación enviada a '.$recipientCount.' destinatarios por app y correo.',
            default => 'Notificación enviada a '.$recipientCount.' destinatarios.',
        };

        return redirect()
            ->route('admin.communications.notifications.show', $appNotification)
            ->with('status', $message);
    }

    public function favorites(Request $request): View
    {
        $search = $request->string('search')->toString();
        $teams = FavoriteTeam::query()
            ->with(['user', 'team.category', 'team.delegation'])
            ->when(
                ! $request->user()?->canAccessAllTournaments(),
                fn ($query) => $query->whereHas('team', fn ($team) => $team->whereIn('tournament_id', $request->user()->assignedTournamentIds() ?: [0]))
            )
            ->latest()
            ->get();
        $matches = FavoriteMatch::query()
            ->with(['user', 'match.homeTeam', 'match.awayTeam', 'match.category'])
            ->when(
                ! $request->user()?->canAccessAllTournaments(),
                fn ($query) => $query->whereHas('match', fn ($match) => $match->whereIn('tournament_id', $request->user()->assignedTournamentIds() ?: [0]))
            )
            ->latest()
            ->get();

        if ($search !== '') {
            $teams = $teams->filter(fn (FavoriteTeam $favorite) => TextSearch::matches(
                $search,
                $favorite->user?->name,
                $favorite->team?->name,
                $favorite->team?->category?->name
            ))->values();
            $matches = $matches->filter(fn (FavoriteMatch $favorite) => TextSearch::matches(
                $search,
                $favorite->user?->name,
                $favorite->match?->title(),
                $favorite->match?->stage
            ))->values();
        }

        return view('admin.communications.favorites', [
            'tab' => 'favorites',
            'teams' => $teams,
            'matches' => $matches,
            'stats' => [
                [$teams->count(), 'Equipos favoritos', 'cyan'],
                [$matches->count(), 'Partidos favoritos', 'green'],
                [$teams->pluck('user_id')->merge($matches->pluck('user_id'))->unique()->count(), 'Usuarios', 'blue'],
                [$teams->pluck('team_id')->unique()->count(), 'Equipos seguidos', 'orange'],
            ],
        ]);
    }

    public function plaques(Request $request): View
    {
        $search = $request->string('search')->toString();
        $posts = ContentPost::query()->accessibleTo($request->user())->where('type', 'plaque')->latest()->get();
        $sheets = MatchSheet::query()
            ->accessibleTo($request->user())
            ->with(['match.homeTeam', 'match.awayTeam', 'match.category'])
            ->where('published', true)
            ->latest()
            ->get();

        if ($search !== '') {
            $posts = $posts->filter(fn (ContentPost $post) => TextSearch::matches($search, $post->title, $post->statusLabel()))->values();
            $sheets = $sheets->filter(fn (MatchSheet $sheet) => TextSearch::matches(
                $search,
                $sheet->match?->title(),
                $sheet->scoreLine(),
                $sheet->match?->category?->name
            ))->values();
        }

        return view('admin.communications.plaques', [
            'tab' => 'plaques',
            'posts' => $posts,
            'sheets' => $sheets,
            'kinds' => PlaqueFactory::kinds(),
            'matches' => FixtureMatch::query()
                ->accessibleTo($request->user())
                ->with(['homeTeam', 'awayTeam', 'category'])
                ->orderByDesc('scheduled_at')
                ->limit(40)
                ->get(),
            'categories' => Category::query()->accessibleTo($request->user())->orderBy('name')->get(),
            'players' => Player::query()
                ->whereHas('team', fn ($query) => $request->user()->canAccessAllTournaments()
                    ? $query
                    : $query->whereIn('tournament_id', $request->user()->assignedTournamentIds() ?: [0]))
                ->orderBy('last_name')
                ->limit(80)
                ->get(),
            'rounds' => FixtureMatch::query()
                ->accessibleTo($request->user())
                ->whereNotNull('round')
                ->distinct()
                ->orderBy('round')
                ->pluck('round'),
        ]);
    }

    public function generatePlaque(Request $request, PlaqueFactory $factory): RedirectResponse
    {
        $data = $request->validate([
            'kind' => ['required', 'string', 'in:'.implode(',', array_keys(PlaqueFactory::kinds()))],
            'match_id' => ['nullable', 'exists:matches,id'],
            'category_id' => ['nullable', 'exists:categories,id'],
            'player_id' => ['nullable', 'exists:players,id'],
            'round' => ['nullable', 'string', 'max:80'],
            'side' => ['nullable', 'string', 'in:home,away'],
        ]);

        $post = $factory->generate($data['kind'], $data, (int) auth()->id());
        $this->assertPostAccess($post);
        $this->audit($post, 'plaque', 'Placa generada: '.$post->title);

        return redirect()
            ->route('admin.communications.plaque', $post)
            ->with('status', 'Placa generada: '.$post->title);
    }

    public function plaque(ContentPost $post): View
    {
        $this->assertPostAccess($post);
        abort_unless($post->type === 'plaque' || $post->status === 'published', 404);

        return view('admin.communications.plaque', compact('post'));
    }

    private function form(ContentPost $post): View
    {
        return view('admin.communications.form', [
            'post' => $post,
            'method' => $post->exists ? 'PUT' : 'POST',
            'url' => $post->exists ? route('admin.communications.update', $post) : route('admin.communications.store'),
            'tournaments' => auth()->user()->accessibleTournaments(),
            'categories' => Category::query()->accessibleTo(auth()->user())->orderBy('name')->get(),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function postPayload(Request $request, ?ContentPost $post = null): array
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:180'],
            'type' => ['required', 'string', 'in:news,announcement,official,plaque'],
            'summary' => ['nullable', 'string', 'max:280'],
            'body' => ['nullable', 'string', 'max:8000'],
            'audience' => ['required', 'string', 'in:public,all,delegates,referees,staff'],
            'status' => ['required', 'string', 'in:draft,scheduled,published,archived'],
            'tournament_id' => ['nullable', 'exists:tournaments,id'],
            'category_id' => ['nullable', 'exists:categories,id'],
            'scheduled_at' => ['nullable', 'date'],
            'pinned' => ['nullable', 'boolean'],
            'cover_file' => ['nullable', 'image', 'max:4096'],
        ]);

        unset($data['cover_file']);

        $data['author_id'] = $post?->author_id ?: auth()->id();
        $data['slug'] = ContentPost::makeSlug($data['title'], $post?->id);
        $data['pinned'] = $request->boolean('pinned');
        $data['tournament_id'] = $data['tournament_id'] ?? null;
        $data['category_id'] = $data['category_id'] ?? null;
        abort_unless(
            ! $data['tournament_id'] || $request->user()?->canAccessTournament((int) $data['tournament_id']),
            403,
            'Este torneo no está dentro de tu alcance.'
        );
        $data['scheduled_at'] = $data['scheduled_at'] ?? null;
        $data['published_at'] = $data['status'] === 'published'
            ? ($post?->published_at ?: now())
            : $post?->published_at;

        return $data;
    }

    private function storeCover(Request $request, ContentPost $post): void
    {
        if (! $request->hasFile('cover_file')) {
            return;
        }

        $file = $request->file('cover_file');
        $directory = public_path('images/content');
        $filename = Str::slug(pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME)).'-'.Str::random(8).'.'.$file->getClientOriginalExtension();
        File::ensureDirectoryExists($directory);
        $file->move($directory, $filename);

        $path = 'images/content/'.$filename;
        $post->update(['cover_path' => $path]);

        MediaAsset::create([
            'content_post_id' => $post->id,
            'tournament_id' => $post->tournament_id,
            'uploaded_by' => auth()->id(),
            'type' => 'image',
            'path' => $path,
            'original_name' => $file->getClientOriginalName(),
            'caption' => $post->title,
        ]);
    }

    private function audit(ContentPost $post, string $action, string $description): void
    {
        AuditLog::create([
            'user_id' => auth()->id(),
            'module' => 'Comunicaciones',
            'action' => $action,
            'description' => $description,
            'auditable_type' => ContentPost::class,
            'auditable_id' => $post->id,
            'metadata' => ['title' => $post->title, 'status' => $post->status, 'type' => $post->type],
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ]);
    }

    private function auditNotification(AppNotification $notification, string $action, string $description): void
    {
        AuditLog::create([
            'user_id' => auth()->id(),
            'module' => 'Comunicaciones',
            'action' => $action,
            'description' => $description,
            'auditable_type' => AppNotification::class,
            'auditable_id' => $notification->id,
            'metadata' => ['title' => $notification->title, 'audience' => $notification->audience],
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ]);
    }

    private function assertPostAccess(ContentPost $post): void
    {
        abort_unless(
            $post->tournament_id === null || auth()->user()?->canAccessTournament((int) $post->tournament_id),
            403,
            'Este contenido no está dentro de tu alcance.'
        );
    }

    private function assertNotificationAccess(AppNotification $notification): void
    {
        abort_unless(
            $notification->tournament_id === null || auth()->user()?->canAccessTournament((int) $notification->tournament_id),
            403,
            'Esta notificación no está dentro de tu alcance.'
        );
    }
}
