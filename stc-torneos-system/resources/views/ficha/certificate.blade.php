<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Constancia de autorización · {{ $player->fullName() }}</title>
    <style>
        body { margin: 28px; font-family: Inter, Arial, sans-serif; color: #111; line-height: 1.5; }
        h1 { font-size: 22px; margin: 0 0 6px; text-transform: uppercase; }
        h2 { font-size: 15px; margin: 22px 0 8px; }
        p, li, td, th { font-size: 13px; }
        .meta { margin: 0 0 18px; color: #333; }
        .box { border: 1px solid #ccc; border-radius: 8px; padding: 14px 16px; margin: 12px 0; white-space: pre-wrap; }
        table { width: 100%; border-collapse: collapse; margin: 16px 0; }
        th, td { border: 1px solid #ccc; padding: 8px 10px; text-align: left; vertical-align: top; }
        .stamp { margin-top: 24px; padding: 12px 14px; background: #f4f8ff; border: 1px solid #9ec5ff; border-radius: 8px; }
        .actions { margin-top: 24px; }
        @media print { .actions { display: none; } }
    </style>
</head>
<body>
    <h1>Constancia de autorización del tutor</h1>
    <p class="meta">
        {{ $player->team?->tournament?->name ?? 'STC Torneos' }} ·
        {{ $player->team?->name ?? '—' }} ·
        {{ $player->team?->category?->name ?? '—' }}
    </p>

    <table>
        <tbody>
            <tr><th>Jugador/a</th><td>{{ $context['player_name'] }}</td></tr>
            <tr><th>D.N.I. jugador/a</th><td>{{ $context['player_document'] }}</td></tr>
            <tr><th>Tutor responsable</th><td>{{ $context['guardian_name'] }}</td></tr>
            <tr><th>D.N.I. tutor</th><td>{{ $context['guardian_document'] }}</td></tr>
            <tr><th>Vínculo</th><td>{{ $guardian->relationship }}</td></tr>
            <tr><th>Email tutor</th><td>{{ $guardian->email ?: '—' }}</td></tr>
            <tr><th>Fecha y hora de aceptación</th><td>{{ $signedAt->format('d/m/Y H:i') }}</td></tr>
        </tbody>
    </table>

    <div class="stamp">
        <strong>Validación STC:</strong>
        El/La tutor/a identificado/a arriba aceptó expresamente los términos y condiciones del torneo,
        la autorización de participación, el uso de imagen y la declaración de aptitud médica
        mediante el enlace digital de autorización.
    </div>

    <h2>1. Autorización y aceptación de responsabilidad</h2>
    <div class="box">{{ $participationText }}</div>

    <h2>2. Autorización de uso de imagen</h2>
    <div class="box">{{ $imageText }}</div>

    <h2>3. Aptitud médica</h2>
    <div class="box">{{ $medicalText }}</div>

    @if ($invitation)
        <p class="meta">Referencia de enlace: {{ $invitation->token }} · Estado: {{ $invitation->familyStatusLabel() }}</p>
    @endif

    <div class="actions">
        <button type="button" onclick="window.print()">Imprimir / guardar PDF</button>
    </div>
</body>
</html>
