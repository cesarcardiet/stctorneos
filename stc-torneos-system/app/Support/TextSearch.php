<?php

namespace App\Support;

class TextSearch
{
    public static function matches(string $needle, mixed ...$haystacks): bool
    {
        $needle = mb_strtolower(trim($needle));

        if ($needle === '') {
            return true;
        }

        foreach ($haystacks as $haystack) {
            if ($haystack === null || $haystack === '') {
                continue;
            }

            if (str_contains(mb_strtolower((string) $haystack), $needle)) {
                return true;
            }
        }

        return false;
    }
}
