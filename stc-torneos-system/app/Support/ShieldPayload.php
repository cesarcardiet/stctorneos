<?php

namespace App\Support;

use Illuminate\Http\Request;

class ShieldPayload
{
    /**
     * @return array{0: string, 1: string}|null
     */
    public static function fromRequest(Request $request, string $fileKey = 'shield_file', string $dataKey = 'shield_data'): ?array
    {
        $binary = null;
        $ext = 'png';
        $data = trim((string) $request->input($dataKey, ''));

        if ($data !== '' && preg_match('#^data:image/(png|jpe?g|webp|gif);base64,([A-Za-z0-9+/=\s]+)$#i', $data, $matches)) {
            $ext = strtolower($matches[1]);
            if ($ext === 'jpeg') {
                $ext = 'jpg';
            }
            $binary = base64_decode(preg_replace('/\s+/', '', $matches[2]), true);
        }

        if ((! is_string($binary) || $binary === '') && $request->hasFile($fileKey)) {
            $file = $request->file($fileKey);
            if ($file?->isValid()) {
                $ext = strtolower($file->getClientOriginalExtension() ?: $file->extension() ?: 'png');
                if ($ext === 'jpeg') {
                    $ext = 'jpg';
                }
                $path = $file->getRealPath();
                $binary = $path ? file_get_contents($path) : $file->get();
            }
        }

        if (! is_string($binary) || strlen($binary) < 24) {
            return null;
        }

        if (! in_array($ext, ['png', 'jpg', 'webp', 'gif'], true)) {
            $ext = 'png';
        }

        return [$binary, $ext];
    }
}
