<?php

namespace App\Services;

use App\Mail\AppNotificationMail;
use App\Models\AppNotification;
use App\Models\AppNotificationRecipient;
use App\Models\User;
use App\Support\StcMail;
use Illuminate\Support\Collection;

class NotificationDispatchService
{
    public function dispatch(AppNotification $notification, ?User $actor = null): int
    {
        $users = $notification->resolveAudienceUsers($actor);
        $notification->recipients()->delete();

        foreach ($users as $user) {
            AppNotificationRecipient::create([
                'app_notification_id' => $notification->id,
                'user_id' => $user->id,
                'role_name' => $user->roleLabel(),
                'status' => 'sent',
            ]);
        }

        $emailsSent = 0;

        if ($this->shouldSendEmail($notification)) {
            foreach ($users as $user) {
                if (StcMail::send(new AppNotificationMail($notification), $user->email)) {
                    $emailsSent++;
                }
            }
        }

        $notification->update([
            'status' => 'sent',
            'sent_at' => now(),
            'recipients_count' => $users->count(),
            'read_count' => 0,
        ]);

        return $users->count();
    }

    public function shouldSendEmail(AppNotification $notification): bool
    {
        return in_array($notification->channel, ['email', 'both'], true);
    }

    /**
     * @return Collection<int, AppNotification>
     */
    public function dueScheduled(): Collection
    {
        return AppNotification::query()
            ->where('status', 'scheduled')
            ->whereNotNull('scheduled_at')
            ->where('scheduled_at', '<=', now())
            ->get();
    }
}
