<?php

namespace App\Support;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class StcMail
{
    public static function send(mixed $mailable, ?string $to): bool
    {
        $to = trim((string) $to);

        if ($to === '' || ! filter_var($to, FILTER_VALIDATE_EMAIL)) {
            return false;
        }

        try {
            Mail::to($to)->send($mailable);

            return true;
        } catch (\Throwable $exception) {
            Log::error('STC mail failed', [
                'to' => $to,
                'mailable' => $mailable::class,
                'error' => $exception->getMessage(),
            ]);

            return false;
        }
    }
}
