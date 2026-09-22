<?php

namespace App\Support;

class Countries
{
    /**
     * @return array<string, string>
     */
    public static function all(): array
    {
        return [
            'AR' => 'Argentina',
            'BO' => 'Bolivia',
            'BR' => 'Brasil',
            'CL' => 'Chile',
            'CO' => 'Colombia',
            'CR' => 'Costa Rica',
            'CU' => 'Cuba',
            'DO' => 'República Dominicana',
            'EC' => 'Ecuador',
            'SV' => 'El Salvador',
            'ES' => 'España',
            'US' => 'Estados Unidos',
            'GT' => 'Guatemala',
            'HN' => 'Honduras',
            'IT' => 'Italia',
            'MX' => 'México',
            'NI' => 'Nicaragua',
            'PA' => 'Panamá',
            'PY' => 'Paraguay',
            'PE' => 'Perú',
            'PT' => 'Portugal',
            'PR' => 'Puerto Rico',
            'UY' => 'Uruguay',
            'VE' => 'Venezuela',
        ];
    }

    /**
     * @return list<string>
     */
    public static function codes(): array
    {
        return array_keys(self::all());
    }

    public static function name(?string $code): string
    {
        $code = strtoupper(trim((string) $code));

        return self::all()[$code] ?? '';
    }

    public static function codeFromName(?string $name): ?string
    {
        $name = mb_strtolower(trim((string) $name));
        if ($name === '') {
            return null;
        }

        $aliases = [
            'argentina' => 'AR',
            'brasil' => 'BR',
            'brazil' => 'BR',
            'chile' => 'CL',
            'bolivia' => 'BO',
            'colombia' => 'CO',
            'paraguay' => 'PY',
            'uruguay' => 'UY',
            'peru' => 'PE',
            'perú' => 'PE',
            'venezuela' => 'VE',
            'mexico' => 'MX',
            'méxico' => 'MX',
            'ecuador' => 'EC',
            'espana' => 'ES',
            'españa' => 'ES',
            'eeuu' => 'US',
            'ee.uu.' => 'US',
            'usa' => 'US',
            'estados unidos' => 'US',
            'italia' => 'IT',
            'portugal' => 'PT',
        ];

        if (isset($aliases[$name])) {
            return $aliases[$name];
        }

        foreach (self::all() as $code => $label) {
            if (mb_strtolower($label) === $name) {
                return $code;
            }
        }

        return null;
    }

    public static function guessCode(?string ...$hints): ?string
    {
        foreach ($hints as $hint) {
            $hint = trim((string) $hint);
            if ($hint === '') {
                continue;
            }

            if (strlen($hint) === 2 && isset(self::all()[strtoupper($hint)])) {
                return strtoupper($hint);
            }

            $code = self::codeFromName($hint);
            if ($code) {
                return $code;
            }
        }

        return null;
    }

    public static function flagUrl(?string $code): ?string
    {
        $code = strtolower(trim((string) $code));
        if (strlen($code) !== 2) {
            return null;
        }

        $png = 'images/flags/'.$code.'.png';
        if (is_file(public_path($png))) {
            return asset($png);
        }

        $local = 'images/flags/'.$code.'.svg';
        if (is_file(public_path($local))) {
            return asset($local);
        }

        return 'https://flagcdn.com/w80/'.$code.'.png';
    }

    /**
     * @return array<string, list<string>>
     */
    public static function grouped(): array
    {
        return [
            'Sudamérica' => ['AR', 'BO', 'BR', 'CL', 'CO', 'EC', 'PY', 'PE', 'UY', 'VE'],
            'Centroamérica y Caribe' => ['CR', 'CU', 'DO', 'SV', 'GT', 'HN', 'NI', 'PA', 'PR'],
            'Norteamérica' => ['MX', 'US'],
            'Europa' => ['ES', 'IT', 'PT'],
        ];
    }

    public static function emoji(?string $code): string
    {
        $code = strtoupper(trim((string) $code));
        if (strlen($code) !== 2 || ! ctype_alpha($code)) {
            return '';
        }

        return mb_chr(0x1F1E6 + ord($code[0]) - 65).mb_chr(0x1F1E6 + ord($code[1]) - 65);
    }
}
