<?php

namespace App\Support;

use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

class ReportPdf
{
    /**
     * @param  array<string, mixed>  $data
     */
    public static function render(
        Request $request,
        string $view,
        array $data,
        string $basename,
        string $orientation = 'portrait',
    ): View|Response {
        if ($request->string('format')->toString() === 'pdf') {
            $pdf = Pdf::loadView($view, array_merge($data, ['pdfMode' => true]))
                ->setPaper('a4', $orientation);

            return $pdf->download(self::filename($basename));
        }

        return view($view, array_merge($data, ['pdfMode' => false]));
    }

    public static function filename(string $basename): string
    {
        $slug = preg_replace('/[^a-z0-9\-]+/i', '-', $basename) ?: 'reporte';
        $slug = trim($slug, '-');

        return strtolower($slug).'.pdf';
    }
}
