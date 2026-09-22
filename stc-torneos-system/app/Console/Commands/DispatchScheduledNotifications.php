<?php

namespace App\Console\Commands;

use App\Services\NotificationDispatchService;
use Illuminate\Console\Command;

class DispatchScheduledNotifications extends Command
{
    protected $signature = 'stc:dispatch-scheduled-notifications';

    protected $description = 'Envía notificaciones programadas cuya fecha ya venció';

    public function handle(NotificationDispatchService $dispatcher): int
    {
        $count = 0;

        foreach ($dispatcher->dueScheduled() as $notification) {
            $dispatcher->dispatch($notification);
            $count++;
            $this->line('Enviada: '.$notification->title);
        }

        $this->info($count > 0 ? "Notificaciones enviadas: {$count}" : 'No había notificaciones programadas pendientes.');

        return self::SUCCESS;
    }
}
