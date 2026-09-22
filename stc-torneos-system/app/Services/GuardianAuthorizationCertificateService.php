<?php

namespace App\Services;

use App\Models\Guardian;
use App\Models\Invitation;
use App\Models\Player;
use App\Models\PlayerDocument;
use App\Support\GuardianAuthorizationTexts;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class GuardianAuthorizationCertificateService
{
    public function generate(Player $player, Guardian $guardian, ?Invitation $invitation = null): PlayerDocument
    {
        $player->loadMissing(['team.tournament', 'team.category', 'documents']);
        $context = GuardianAuthorizationTexts::context($player, $guardian);
        $signedAt = now();

        $html = view('ficha.certificate', [
            'player' => $player,
            'guardian' => $guardian,
            'context' => $context,
            'signedAt' => $signedAt,
            'invitation' => $invitation,
            'participationText' => GuardianAuthorizationTexts::participation($context),
            'imageText' => GuardianAuthorizationTexts::imageUse($context),
            'medicalText' => GuardianAuthorizationTexts::medicalFitness($context),
        ])->render();

        $directory = public_path('images/players/docs');
        File::ensureDirectoryExists($directory);

        $filename = 'constancia-tutor-'.$player->id.'-'.Str::random(10).'.html';
        $path = 'images/players/docs/'.$filename;
        File::put(public_path($path), $html);

        $document = $player->documents()->firstOrCreate(
            ['type' => Player::guardianCertificateType()],
            ['status' => 'pending']
        );

        $oldPath = $document->file_path;
        $document->update([
            'file_path' => $path,
            'original_name' => 'constancia-autorizacion-'.Str::slug($player->fullName()).'.html',
            'uploaded_by_name' => $guardian->name,
            'uploaded_at' => $signedAt,
            'reviewed_at' => $signedAt,
            'status' => 'approved',
            'notes' => sprintf(
                'Constancia generada automáticamente. Tutor %s (DNI %s) aceptó términos y condiciones el %s.',
                $context['guardian_name'],
                $context['guardian_document'],
                $signedAt->format('d/m/Y H:i')
            ),
            'checklist' => [
                'identity_match' => 'ok',
                'tutor_signed' => 'ok',
                'expiration_valid' => 'ok',
                'file_readable' => 'ok',
                'fit_to_play' => 'ok',
            ],
        ]);

        if ($oldPath && $oldPath !== $path && str_starts_with((string) $oldPath, 'images/players/docs/') && is_file(public_path($oldPath))) {
            File::delete(public_path($oldPath));
        }

        return $document->fresh();
    }
}
