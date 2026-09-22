<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Workspace\Concerns\ResolvesWorkspace;
use App\Models\Category;
use App\Models\Guardian;
use App\Models\Invitation;
use App\Models\Player;
use App\Models\Team;
use App\Services\GuardianInvitationService;
use App\Support\Countries;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class PlayerRosterController extends ApiController
{
    use ResolvesWorkspace;

    public function context(Request $request): JsonResponse
    {
        abort_unless($this->canManageRoster($request->user()), 403, 'No podés gestionar planteles.');

        $teams = Team::query()
            ->accessibleTo($request->user())
            ->with(['category.tournament', 'delegation'])
            ->orderBy('name')
            ->get()
            ->unique('id')
            ->map(function (Team $team) {
                $payload = $this->teamPayload($team);
                $payload['category_id'] = $team->category_id;
                $payload['tournament_id'] = $team->category?->tournament_id;
                $payload['tournament_name'] = $team->category?->tournament?->name;
                $payload['roster_editable'] = (bool) $team->category?->acceptsRosterEdits($team);

                return $payload;
            });

        return $this->ok([
            'teams' => $teams->values(),
            'options' => $this->formOptions(),
        ]);
    }

    public function show(Request $request, Category $category, Player $player): JsonResponse
    {
        $category = $this->assertCategory($category);
        $player = $this->assertPlayerInCategory($category, $player);
        abort_unless($this->canEditPlayer($request->user(), $player), 403, 'No podés editar este jugador.');

        return $this->ok($this->rosterPlayerPayload($player));
    }

    public function store(Request $request, Category $category): JsonResponse
    {
        $category = $this->assertCategory($category);
        abort_unless($this->canManageRoster($request->user()), 403);

        $data = $request->validate([
            'team_id' => ['required', 'exists:teams,id'],
            'first_name' => ['required', 'string', 'max:120'],
            'last_name' => ['required', 'string', 'max:120'],
            'document_number' => ['nullable', 'string', 'max:40'],
            'birth_date' => ['nullable', 'date', 'after_or_equal:1990-01-01', 'before_or_equal:today'],
            'nationality' => ['nullable', 'string', Rule::in(array_values(Countries::all()))],
            'address' => ['nullable', 'string', 'max:255'],
            'position' => ['nullable', 'string', Rule::in(Player::positions())],
            'jersey_number' => ['nullable', 'integer', 'min:1', 'max:99'],
            'kit_size' => ['nullable', 'string', Rule::in(Player::kitSizes())],
            'preferred_foot' => ['nullable', 'string', Rule::in(Player::preferredFeet())],
            'height' => ['nullable', 'string', 'max:40'],
            'weight' => ['nullable', 'string', 'max:40'],
            'guardian_name' => ['nullable', 'string', 'max:160'],
            'guardian_phone' => ['nullable', 'string', 'max:80'],
            'guardian_email' => ['nullable', 'email', 'max:180'],
            'guardian_relationship' => ['nullable', 'string', Rule::in(Guardian::relationshipOptions())],
        ]);

        $team = Team::query()
            ->where('category_id', $category->id)
            ->findOrFail($data['team_id']);
        $this->assertDelegateRosterEditable($request->user(), $team);
        abort_unless(
            Team::query()->accessibleTo($request->user())->whereKey($team->id)->exists(),
            403,
            'Este plantel no está dentro de tu alcance.'
        );

        if (filled($data['guardian_name'] ?? null)
            && ! filled($data['guardian_phone'] ?? null)
            && ! filled($data['guardian_email'] ?? null)) {
            throw ValidationException::withMessages([
                'guardian_phone' => 'Indicá teléfono o email del tutor.',
            ]);
        }

        $player = Player::create([
            'team_id' => $team->id,
            'first_name' => trim($data['first_name']),
            'last_name' => trim($data['last_name']),
            'document_number' => $data['document_number'] ?? null,
            'birth_date' => $data['birth_date'] ?? null,
            'nationality' => $data['nationality'] ?? 'Argentina',
            'address' => $data['address'] ?? null,
            'position' => $data['position'] ?? null,
            'jersey_number' => $data['jersey_number'] ?? null,
            'kit_size' => $data['kit_size'] ?? null,
            'preferred_foot' => $data['preferred_foot'] ?? null,
            'height' => $data['height'] ?? null,
            'weight' => $data['weight'] ?? null,
            'status' => filled($data['guardian_name'] ?? null) ? 'awaiting_guardian' : 'in_progress',
        ]);

        foreach (Player::documentTypes() as $type) {
            $player->documents()->firstOrCreate(['type' => $type], ['status' => 'pending']);
        }

        app(GuardianInvitationService::class)->ensureGuardianFromPlayerForm($player, $data);

        return $this->ok(
            $this->rosterPlayerPayload($player->fresh(['team.category', 'team.delegation', 'guardian', 'documents'])),
            'Jugador creado.',
            201
        );
    }

    public function update(Request $request, Category $category, Player $player): JsonResponse
    {
        $category = $this->assertCategory($category);
        $player = $this->assertPlayerInCategory($category, $player);
        $player->loadMissing('team.category');
        $user = $request->user();

        abort_unless($this->canEditPlayer($user, $player), 403, 'No podés editar este jugador.');
        $this->assertDelegateRosterEditable($user, $player->team);
        abort_unless($this->canManageRoster($user), 403);
        abort_unless(
            Team::query()->accessibleTo($user)->whereKey($player->team_id)->exists(),
            403,
            'Este plantel no está dentro de tu alcance.'
        );

        $data = $request->validate([
            'first_name' => ['sometimes', 'required', 'string', 'max:120'],
            'last_name' => ['sometimes', 'required', 'string', 'max:120'],
            'document_number' => ['nullable', 'string', 'max:40'],
            'birth_date' => ['nullable', 'date', 'after_or_equal:1990-01-01', 'before_or_equal:today'],
            'nationality' => ['nullable', 'string', Rule::in(array_values(Countries::all()))],
            'address' => ['nullable', 'string', 'max:255'],
            'position' => ['nullable', 'string', Rule::in(Player::positions())],
            'jersey_number' => ['nullable', 'integer', 'min:1', 'max:99'],
            'kit_size' => ['nullable', 'string', Rule::in(Player::kitSizes())],
            'preferred_foot' => ['nullable', 'string', Rule::in(Player::preferredFeet())],
            'height' => ['nullable', 'string', 'max:40'],
            'weight' => ['nullable', 'string', 'max:40'],
            'blood_type' => ['nullable', 'string', Rule::in(Player::bloodTypes())],
            'medical_coverage' => ['nullable', 'string', 'max:120'],
            'allergies' => ['nullable', 'string', 'max:180'],
            'medication' => ['nullable', 'string', 'max:180'],
            'illnesses' => ['nullable', 'string', 'max:180'],
            'restrictions' => ['nullable', 'string', 'max:180'],
            'emergency_contact' => ['nullable', 'string', 'max:180'],
            'medical_notes' => ['nullable', 'string', 'max:1200'],
            'vaccination_calendar_complete' => ['nullable', 'boolean'],
            'ongoing_treatment' => ['nullable', 'boolean'],
            'ongoing_treatment_notes' => ['nullable', 'string', 'max:1200'],
            'guardian_name' => ['nullable', 'string', 'max:160'],
            'guardian_document_number' => ['nullable', 'string', 'max:40'],
            'guardian_relationship' => ['nullable', 'string', Rule::in(Guardian::relationshipOptions())],
            'guardian_phone' => ['nullable', 'string', 'max:80'],
            'guardian_email' => ['nullable', 'email', 'max:180'],
            'guardian_alternate_contact' => ['nullable', 'string', 'max:255'],
        ]);

        $payload = [];
        foreach ([
            'first_name',
            'last_name',
            'document_number',
            'birth_date',
            'nationality',
            'address',
            'position',
            'jersey_number',
            'kit_size',
            'preferred_foot',
            'height',
            'weight',
            'blood_type',
            'medical_coverage',
            'allergies',
            'medication',
            'illnesses',
            'restrictions',
            'emergency_contact',
            'medical_notes',
        ] as $field) {
            if ($request->exists($field)) {
                $payload[$field] = $data[$field] ?? null;
            }
        }

        if ($request->exists('vaccination_calendar_complete')) {
            $payload['vaccination_calendar_complete'] = $request->boolean('vaccination_calendar_complete');
        }

        if ($request->exists('ongoing_treatment')) {
            $payload['ongoing_treatment'] = $request->boolean('ongoing_treatment');
            $payload['ongoing_treatment_notes'] = $payload['ongoing_treatment'] === true
                ? ($data['ongoing_treatment_notes'] ?? null)
                : null;
        } elseif ($request->exists('ongoing_treatment_notes')) {
            $payload['ongoing_treatment_notes'] = $data['ongoing_treatment_notes'] ?? null;
        }

        if ($payload !== []) {
            $player->update($payload);
        }

        app(GuardianInvitationService::class)->ensureGuardianFromPlayerForm($player->fresh('guardian'), $data);

        if (in_array($player->status, ['draft', 'pending'], true) && filled($data['guardian_name'] ?? $player->guardian?->name)) {
            $player->update(['status' => 'awaiting_guardian']);
        } elseif ($player->status === 'draft') {
            $player->update(['status' => 'in_progress']);
        }

        return $this->ok($this->rosterPlayerPayload($player->fresh(['team.category', 'team.delegation', 'guardian', 'documents'])), 'Ficha actualizada.');
    }

    public function uploadDocument(Request $request, Category $category, Player $player): JsonResponse
    {
        $category = $this->assertCategory($category);
        $player = $this->assertPlayerInCategory($category, $player);
        $player->loadMissing('team');
        $this->assertDelegateRosterEditable($request->user(), $player->team);
        abort_unless($this->canEditFullPlayer($request->user()), 403);
        abort_unless(
            Team::query()->accessibleTo($request->user())->whereKey($player->team_id)->exists(),
            403,
            'Este plantel no está dentro de tu alcance.'
        );

        $data = $request->validate([
            'type' => ['required', 'string', Rule::in(Player::uploadDocumentTypes())],
            'file' => ['required', 'image', 'max:8192'],
        ]);

        $player->ensureDocuments();
        $document = $player->documents()->firstOrCreate(
            ['type' => $data['type']],
            ['status' => 'pending']
        );
        $document->storeImage($request->file('file'), $request->user()->name);

        if ($data['type'] === 'Foto del jugador') {
            $player->update(['photo_path' => $document->file_path]);
        }

        return $this->ok([
            'document' => $this->documentPayload($document->fresh()),
            'player' => $this->rosterPlayerPayload($player->fresh(['team.category', 'team.delegation', 'guardian', 'documents'])),
        ], $data['type'].' cargado.');
    }

    public function inviteGuardian(Request $request, Category $category, Player $player): JsonResponse
    {
        $category = $this->assertCategory($category);
        $player = $this->assertPlayerInCategory($category, $player);
        $player->loadMissing('team');
        $this->assertDelegateRosterEditable($request->user(), $player->team);
        abort_unless($this->canManageRoster($request->user()), 403);
        abort_unless(
            Team::query()->accessibleTo($request->user())->whereKey($player->team_id)->exists(),
            403,
            'Este plantel no está dentro de tu alcance.'
        );

        $data = $request->validate([
            'email' => ['required', 'email', 'max:180'],
        ]);

        $player->load('guardian');
        app(GuardianInvitationService::class)->ensureGuardianFromPlayerForm($player, [
            'guardian_email' => $data['email'],
            'guardian_name' => $player->guardian?->name,
            'guardian_relationship' => $player->guardian?->relationship,
            'guardian_phone' => $player->guardian?->phone,
            'guardian_document_number' => $player->guardian?->document_number,
            'consent_status' => $player->guardian?->consent_status ?? 'pending',
        ]);

        $invitation = app(GuardianInvitationService::class)->create(
            $player->fresh('guardian'),
            $data['email'],
            $request->user()->id
        );

        return $this->ok([
            'invitation' => $this->invitationPayload($invitation),
            'player' => $this->rosterPlayerPayload($player->fresh(['team.category', 'team.delegation', 'guardian', 'documents'])),
        ], 'Enlace de confirmación generado para el tutor.');
    }

    /**
     * @return array<string, mixed>
     */
    private function formOptions(): array
    {
        return [
            'positions' => Player::positions(),
            'kit_sizes' => Player::kitSizeGroups(),
            'preferred_feet' => Player::preferredFeet(),
            'blood_types' => Player::bloodTypes(),
            'document_types' => Player::uploadDocumentTypes(),
            'guardian_relationships' => Guardian::relationshipOptions(),
            'nationalities' => array_values(Countries::all()),
        ];
    }

    private function assertPlayerInCategory(Category $category, Player $player): Player
    {
        $player->loadMissing('team');
        abort_unless((int) $player->team?->category_id === (int) $category->id, 404);

        return $player;
    }

    /**
     * @return array<string, mixed>
     */
    private function rosterPlayerPayload(Player $player): array
    {
        $player->loadMissing(['team.category.tournament', 'team.delegation', 'guardian', 'documents']);
        $invitation = $player->latestGuardianInvitation();

        return [
            'id' => $player->id,
            'first_name' => $player->first_name,
            'last_name' => $player->last_name,
            'name' => $player->fullName(),
            'document_number' => $player->document_number,
            'birth_date' => $player->birth_date?->toDateString(),
            'nationality' => $player->nationality,
            'address' => $player->address,
            'position' => $player->position,
            'jersey_number' => $player->jersey_number,
            'kit_size' => $player->kit_size,
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
            'team' => $player->team ? $this->teamPayload($player->team) : null,
            'category_id' => $player->team?->category_id,
            'guardian' => $player->guardian ? [
                'name' => $player->guardian->name,
                'document_number' => $player->guardian->document_number,
                'relationship' => $player->guardian->relationship,
                'phone' => $player->guardian->phone,
                'email' => $player->guardian->email,
                'alternate_contact' => $player->guardian->alternate_contact,
                'consent_status' => $player->guardian->consent_status,
                'consent_label' => $player->guardian->consentLabel(),
            ] : null,
            'documents' => $player->documents
                ->map(fn ($document) => $this->documentPayload($document))
                ->values(),
            'invitation' => $invitation ? $this->invitationPayload($invitation) : null,
            'layers' => [
                'profile' => $player->layerOneLabel(),
                'guardian' => $player->layerTwoLabel(),
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function documentPayload($document): array
    {
        return [
            'id' => $document->id,
            'type' => $document->type,
            'status' => $document->status,
            'status_label' => $document->statusLabel(),
            'file_url' => $document->fileUrl(),
            'uploaded_at' => $document->uploaded_at?->toIso8601String(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function invitationPayload(Invitation $invitation): array
    {
        return [
            'id' => $invitation->id,
            'email' => $invitation->email,
            'url' => $invitation->publicUrl(),
            'status' => $invitation->family_status,
            'status_label' => $invitation->familyStatusLabel(),
            'expires_at' => $invitation->expires_at?->toIso8601String(),
        ];
    }
}
