<?php

namespace App\Support;

class TutorCredentials
{
    public static function defaultPassword(): string
    {
        return (string) config('app.tutor_default_password', 'stctutor');
    }
}
