<?php

namespace App\Http\Controllers\Api;

use App\Models\Guardian;
use App\Models\Player;
use App\Models\User;
use App\Services\GuardianFichaService;
use App\Services\GuardianInvitationService;
use App\Support\Countries;
use App\Support\GuardianAuthorizationTexts;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TutorFichaApiController extends ApiController
{
    public function show(Request $request, Player $player): JsonResponse
    {
        $user = $request->user();
        abort_unless($user->isTutorAccount(), 403, 'Esta sección es solo para tutores.');
        $player = $this->assertTutorPlayer($user, $player);
        $player->load(['team.category.tournament', 'team.delegation', 'guardian', 'documents', 'invitations']);
        $player->ensureDocuments();

        $locked = $player->tutorFichaLocked();
        $invitation = $player->tutorFichaInvitation();

        if (! $invitation) {
            $invitation = app(GuardianInvitationService::class)->ensureInvitation(
                $player,
                $user->email,
                $user->id,
                sendMail: false
            );
        } elseif (! $locked) {
            $invitation = app(GuardianFichaService::class)->markAccessed($invitation);
        }

        $guardian = $player->guardian ?? new Guardian([
            'relationship' => 'Madre',
            'email' => $user->email,
        ]);
        $authContext = GuardianAuthorizationTexts::context($player, $guardian);

        return $this->ok([
            'locked' => $locked,
            'player' => $this->fichaPlayerPayload($player),
            'guardian' => $this->guardianPayload($guardian),
            'documents' => $player->documents->map(fn ($document) => [
                'type' => $document->type,
                'status' => $document->status,
                'status_label' => $document->statusLabel(),
                'file_url' => $document->fileUrl(),
            ])->values(),
            'invitation' => [
                'family_status' => $invitation->family_status,
                'status' => $invitation->status,
            ],
            'options' => $this->formOptions(),
            'required_document_types' => Player::tutorRequiredDocumentTypes(),
            'upload_document_types' => Player::tutorUploadDocumentTypes(),
            'authorization_types' => Player::authorizationDocumentTypes(),
            'authorization_texts' => [
                'Autorización' => GuardianAuthorizationTexts::participation($authContext),
                'Uso de imagen' => GuardianAuthorizationTexts::imageUse($authContext),
                'Apto médico' => GuardianAuthorizationTexts::medicalFitness($authContext),
            ],
            'progress' => [
                'profile' => $player->layerOneLabel(),
                'guardian' => $player->layerTwoLabel(),
                'documents' => $player->authorizationSummary(),
                'review' => $player->reviewStatusLabel(),
            ],
        ]);
    }

    public function submit(Request $request, Player $player): JsonResponse
    {
        $user = $request->user();
        abort_unless($user->isTutorAccount(), 403, 'Esta sección es solo para tutores.');
        $player = $this->assertTutorPlayer($user, $player);
        $player->load('invitations');

        abort_if(
            $player->tutorFichaLocked(),
            422,
            'Esta ficha ya fue enviada y está en revisión. Si necesitás cambiar algo, pedile al administrador o delegado del torneo.'
        );

        $invitation = $player->tutorFichaInvitation()
            ?? app(GuardianInvitationService::class)->ensureInvitation(
                $player,
                $user->email,
                $user->id,
                sendMail: false
            );

        $result = app(GuardianFichaService::class)->submitForApi($request, $invitation);

        return $this->ok([
            'player' => $this->fichaPlayerPayload($result['player']),
            'message' => $result['message'],
            'locked' => true,
        ], $result['message']);
    }

    private function assertTutorPlayer(User $user, Player $player): Player
    {
        $owned = $user->tutorPlayers()->contains(fn (Player $linked) => $linked->id === $player->id);
        abort_unless($owned, 403, 'Este jugador no está vinculado a tu cuenta de tutor.');

        return $player;
    }

    /**
     * @return array<string, mixed>
     */
    private function fichaPlayerPayload(Player $player): array
    {
        $player->loadMissing(['team.category.tournament', 'team.delegation', 'guardian']);

        return [
            'id' => $player->id,
            'first_name' => $player->first_name,
            'last_name' => $player->last_name,
            'name' => $player->fullName(),
            'document_number' => $player->document_number,
            'email' => $player->email,
            'birth_date' => $player->birth_date?->toDateString(),
            'nationality' => $player->nationality,
            'address' => $player->address,
            'kit_size' => $player->kit_size,
            'jersey_number' => $player->jersey_number,
            'position' => $player->position,
            'preferred_foot' => $player->preferred_foot,
            'height' => $player->height,
            'weight' => $player->weight,
            'blood_type' => $player->blood_type,
            'medical_coverage' => $player->medical_coverage,
            'allergies' => $player->allergies,
            'medication' => $player->medication,
            'illnesses' => $player->illnesses,
            'restrictions' => $player->restrictions,
            'emergency_contact' => $player->emergency_contact,
            'medical_notes' => $player->medical_notes,
            'vaccination_calendar_complete' => $player->vaccination_calendar_complete,
            'ongoing_treatment' => $player->ongoing_treatment,
            'ongoing_treatment_notes' => $player->ongoing_treatment_notes,
            'photo' => $player->photoUrl(),
            'status' => $player->status,
            'status_label' => $player->statusLabel(),
            'category' => $player->team?->category?->name,
            'club' => $player->team?->delegation?->name,
            'tournament' => $player->team?->tournament?->name,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function guardianPayload(Guardian $guardian): array
    {
        $nameParts = preg_split('/\s+/', trim((string) $guardian->name)) ?: [];

        return [
            'name' => $guardian->name,
            'first_name' => $nameParts[0] ?? '',
            'last_name' => count($nameParts) > 1 ? implode(' ', array_slice($nameParts, 1)) : '',
            'document_number' => $guardian->document_number,
            'relationship' => $guardian->relationship,
            'email' => $guardian->email,
            'phone' => $guardian->phone,
            'alternate_contact' => $guardian->alternate_contact,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function formOptions(): array
    {
        return [
            'positions' => Player::positions(),
            'kit_sizes' => Player::kitSizes(),
            'preferred_feet' => Player::preferredFeet(),
            'blood_types' => Player::bloodTypes(),
            'guardian_relationships' => Guardian::relationshipOptions(),
            'nationalities' => array_values(Countries::all()),
        ];
    }
}
