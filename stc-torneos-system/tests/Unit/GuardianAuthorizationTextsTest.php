<?php

namespace Tests\Unit;

use App\Models\Guardian;
use App\Models\Player;
use App\Support\GuardianAuthorizationTexts;
use Tests\TestCase;

class GuardianAuthorizationTextsTest extends TestCase
{
    public function test_participation_text_uses_guardian_player_and_tournament_data(): void
    {
        $player = new Player([
            'first_name' => 'Thiago',
            'last_name' => 'Martínez',
            'document_number' => '50111222',
        ]);

        $guardian = new Guardian([
            'name' => 'María Martínez',
            'document_number' => '27123456',
            'relationship' => 'Madre',
        ]);

        $context = GuardianAuthorizationTexts::context($player, $guardian);
        $text = GuardianAuthorizationTexts::participation($context);

        $this->assertStringContainsString('María Martínez', $text);
        $this->assertStringContainsString('Thiago Martínez', $text);
        $this->assertStringContainsString('50111222', $text);
        $this->assertStringContainsString('AUTORIZACIÓN Y ACEPTACIÓN DE RESPONSABILIDAD', $text);
    }

    public function test_signed_note_includes_guardian_document(): void
    {
        $context = [
            'guardian_name' => 'María Martínez',
            'guardian_document' => '27123456',
        ];

        $note = GuardianAuthorizationTexts::signedNote('Autorización', $context);

        $this->assertStringContainsString('María Martínez', $note);
        $this->assertStringContainsString('27123456', $note);
    }
}
