<?php

namespace App\Events;

use App\Models\User;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class UserNotificationPushed implements ShouldBroadcast
{
    use Dispatchable, SerializesModels;

    /**
     * @param  array{id:int,title:string,body:string,sent_at:?string,url:?string}  $payload
     */
    public function __construct(
        public User $user,
        public array $payload,
    ) {
    }

    /**
     * @return array<int, PrivateChannel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('App.Models.User.'.$this->user->id),
        ];
    }

    public function broadcastAs(): string
    {
        return 'notification.pushed';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return $this->payload;
    }
}
