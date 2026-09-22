<?php

namespace App\Services;

use App\Mail\DocumentReviewedMail;
use App\Mail\GuardianConsentRequestMail;
use App\Mail\PlayerStatusChangedMail;
use App\Models\Player;
use App\Models\PlayerDocument;
use App\Support\StcMail;

class PlayerEmailNotifier
{
    public static function consentRequested(Player $player): bool
    {
        $player->loadMissing('guardian');

        if (! filled($player->guardian?->email)) {
            return false;
        }

        return StcMail::send(new GuardianConsentRequestMail($player), $player->guardian->email);
    }

    public static function statusChanged(Player $player, ?string $note = null): bool
    {
        $player->loadMissing('guardian');

        if (! filled($player->guardian?->email)) {
            return false;
        }

        return StcMail::send(new PlayerStatusChangedMail($player, $note), $player->guardian->email);
    }

    public static function documentReviewed(PlayerDocument $document): bool
    {
        $document->loadMissing('player.guardian');

        if (! filled($document->player?->guardian?->email)) {
            return false;
        }

        return StcMail::send(new DocumentReviewedMail($document), $document->player->guardian->email);
    }
}
