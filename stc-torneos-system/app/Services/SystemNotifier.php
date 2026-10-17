<?php

namespace App\Services;

use App\Models\AppNotification;
use App\Models\User;
use Illuminate\Support\Facades\Auth;

/**
 * Crea y envía avisos automáticos a roles (sin pasar por el formulario manual).
 */
class SystemNotifier
{
    public function __construct(
        private readonly NotificationDispatchService $dispatcher,
    ) {
    }

    /**
     * @param  array<int, string>  $audiences  public|all|delegates|referees|staff
     */
    public function notify(
        string $title,
        string $body,
        string $audience = 'staff',
        ?int $tournamentId = null,
        string $channel = 'in_app',
        ?User $actor = null,
    ): ?AppNotification {
        $actor ??= Auth::user();

        $notification = AppNotification::create([
            'tournament_id' => $tournamentId,
            'created_by' => $actor?->id,
            'channel' => $channel,
            'title' => $title,
            'body' => $body,
            'audience' => $audience,
            'status' => 'draft',
            'recipients_count' => 0,
            'read_count' => 0,
        ]);

        $this->dispatcher->dispatch($notification, $actor);

        return $notification->fresh();
    }

    public function toStaff(string $title, string $body, ?int $tournamentId = null): ?AppNotification
    {
        return $this->notify($title, $body, 'staff', $tournamentId);
    }

    public function toDelegates(string $title, string $body, ?int $tournamentId = null): ?AppNotification
    {
        return $this->notify($title, $body, 'delegates', $tournamentId);
    }

    public function toReferees(string $title, string $body, ?int $tournamentId = null): ?AppNotification
    {
        return $this->notify($title, $body, 'referees', $tournamentId);
    }

    public function toAll(string $title, string $body, ?int $tournamentId = null): ?AppNotification
    {
        return $this->notify($title, $body, 'all', $tournamentId);
    }
}
