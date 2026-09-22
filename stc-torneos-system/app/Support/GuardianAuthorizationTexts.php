<?php

namespace App\Support;

use App\Models\Guardian;
use App\Models\Player;

class GuardianAuthorizationTexts
{
    /**
     * @return array{
     *     guardian_name: string,
     *     guardian_document: string,
     *     relationship: string,
     *     relationship_label: string,
     *     player_name: string,
     *     player_document: string,
     *     team_name: string,
     *     tournament_name: string,
     *     category_name: string
     * }
     */
    public static function context(Player $player, ?Guardian $guardian = null): array
    {
        $relationship = trim((string) ($guardian?->relationship ?: 'Madre'));

        return [
            'guardian_name' => trim((string) ($guardian?->name ?: '')),
            'guardian_document' => trim((string) ($guardian?->document_number ?: '')),
            'relationship' => $relationship,
            'relationship_label' => self::relationshipLegalLabel($relationship),
            'player_name' => $player->fullName(),
            'player_document' => trim((string) ($player->document_number ?: '')),
            'team_name' => trim((string) ($player->team?->name ?: '—')),
            'tournament_name' => trim((string) ($player->team?->tournament?->name ?: 'Santa Teresita Cup')),
            'category_name' => trim((string) ($player->team?->category?->name ?: '')),
        ];
    }

    public static function relationshipLegalLabel(string $relationship): string
    {
        return match (mb_strtolower(trim($relationship))) {
            'padre' => 'padre',
            'madre' => 'madre',
            default => 'padre / madre / tutor',
        };
    }

    /**
     * @param  array<string, string>  $context
     */
    public static function participation(array $context): string
    {
        $guardian = self::value($context, 'guardian_name');
        $relationship = self::value($context, 'relationship_label');
        $player = self::value($context, 'player_name');
        $playerDocument = self::value($context, 'player_document');
        $tournament = self::value($context, 'tournament_name');
        $team = self::value($context, 'team_name');

        return <<<TEXT
AUTORIZACIÓN Y ACEPTACIÓN DE RESPONSABILIDAD

Por medio del presente, yo {$guardian}, en mi carácter de {$relationship} del/la menor {$player}, D.N.I. Nº {$playerDocument}, autorizo expresamente su participación en la {$tournament}, como integrante del equipo {$team}, bajo mi responsabilidad y en conocimiento de las características y condiciones propias de la actividad deportiva.

Asimismo, declaro haber leído y aceptado el Reglamento del Torneo, comprometiéndome a informar y explicar su contenido al/la menor, y a velar por su respeto y cumplimiento durante su participación.

En virtud de lo expuesto, manifiesto conocer y aceptar que la práctica deportiva implica riesgos propios de la actividad y que la participación del/la menor se realiza de manera voluntaria. En tal sentido, libero de responsabilidad a la organizadora del torneo y a las personas que intervengan en su organización, coordinación y desarrollo, respecto de aquellos hechos o accidentes que pudieran producirse como consecuencia de los riesgos propios de la práctica deportiva, sin perjuicio de las responsabilidades que legalmente pudieran corresponder.
TEXT;
    }

    /**
     * @param  array<string, string>  $context
     */
    public static function imageUse(array $context): string
    {
        $player = self::value($context, 'player_name');

        return <<<TEXT
AUTORIZACIÓN DE USO DE IMAGEN

Autorizo expresamente a STC Torneos a realizar, utilizar, publicar y difundir fotografías y/o videos en los que pueda aparecer el/la menor {$player} durante su participación en el torneo y sus actividades relacionadas, a través de las distintas plataformas de comunicación, redes sociales, página web oficial y demás medios de difusión vinculados al torneo, con fines exclusivamente informativos, institucionales y de promoción del evento, sin que dicha autorización genere derecho a compensación económica, indemnización o contraprestación alguna a favor del/la menor o de quien suscribe.
TEXT;
    }

    /**
     * @param  array<string, string>  $context
     */
    public static function medicalFitness(array $context): string
    {
        $relationship = self::value($context, 'relationship_label');
        $player = self::value($context, 'player_name');

        return <<<TEXT
APTITUD MÉDICA

Declaro, en mi carácter de {$relationship} del/la menor, haberme hecho responsable de realizar la correspondiente evaluación y/o revisión médica previa a su participación en el torneo, habiendo sido evaluado/a por un profesional de la salud, quien ha determinado que el/la menor {$player} se encuentra apto/a para realizar la actividad deportiva en la que participará.

Asimismo, dejo constancia de que me responsabilizo por la veracidad de la declaración de aptitud médica del/la menor para la práctica de dicha actividad.
TEXT;
    }

    /**
     * @param  array<string, string>  $context
     */
    public static function signedNote(string $type, array $context): string
    {
        $guardian = self::value($context, 'guardian_name');
        $guardianDocument = self::value($context, 'guardian_document');

        return sprintf(
            '%s aceptada por %s (DNI %s) el %s.',
            $type,
            $guardian,
            $guardianDocument,
            now()->format('d/m/Y H:i')
        );
    }

    /**
     * @param  array<string, string>  $context
     */
    private static function value(array $context, string $key): string
    {
        $value = trim((string) ($context[$key] ?? ''));

        return $value !== '' ? $value : '________________';
    }
}
