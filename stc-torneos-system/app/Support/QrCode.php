<?php

namespace App\Support;

class QrCode
{
    public static function url(string $payload, int $size = 180): string
    {
        return 'https://api.qrserver.com/v1/create-qr-code/?size='.$size.'x'.$size.'&data='.rawurlencode($payload);
    }
}
