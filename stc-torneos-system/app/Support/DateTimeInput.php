<?php

namespace App\Support;

use Illuminate\Http\Request;

class DateTimeInput
{
    public static function merge(Request $request, string $field): void
    {
        $date = trim((string) $request->input($field.'_date', ''));
        $time = trim((string) $request->input($field.'_time', ''));

        if ($date !== '' && $time !== '') {
            $request->merge([$field => $date.' '.$time]);
        }
    }
}
