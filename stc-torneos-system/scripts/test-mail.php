<?php

require __DIR__.'/../vendor/autoload.php';

$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$to = $argv[1] ?? 'cesarcardiet2@gmail.com';

try {
    Illuminate\Support\Facades\Mail::raw(
        'Prueba SMTP STC Torneos - correo operativo desde VPS.',
        fn ($message) => $message->to($to)->subject('Test STC - info@stctorneos.com')
    );
    echo "MAIL_SENT_OK to {$to}\n";
} catch (Throwable $e) {
    echo 'MAIL_ERROR: '.$e->getMessage()."\n";
    exit(1);
}
