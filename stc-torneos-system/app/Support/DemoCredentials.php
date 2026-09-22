<?php

namespace App\Support;

class DemoCredentials
{
    public static function password(): string
    {
        return (string) config('app.demo_password', 'stcdemo');
    }
}
