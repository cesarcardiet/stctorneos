<?php

namespace App\Support;

class WhatsApp
{
    public static function url(?string $phone, ?string $text = null): ?string
    {
        $digits = preg_replace('/\D+/', '', (string) $phone);
        if (str_starts_with((string) $digits, '00')) {
            $digits = substr($digits, 2);
        }

        if ($digits === '' || strlen($digits) < 8) {
            return null;
        }

        if (strlen($digits) <= 11 && ! str_starts_with($digits, '54')) {
            $digits = '54'.$digits;
        }

        $url = 'https://wa.me/'.$digits;
        if (filled($text)) {
            $url .= '?text='.rawurlencode($text);
        }

        return $url;
    }
}
