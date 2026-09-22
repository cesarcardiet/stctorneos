<?php

namespace App\Support;

class PlayerCredentials
{
    public static function defaultPassword(): string
    {
        return (string) config('app.player_default_password', 'stcjugador');
    }
}
