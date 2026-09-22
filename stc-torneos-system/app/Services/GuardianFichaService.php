<?php

namespace App\Services;

use App\Models\Guardian;
use App\Models\Invitation;
use App\Models\Player;
use App\Support\GuardianAuthorizationTexts;
use App\Support\ShieldPayload;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class GuardianFichaService
{
    public function submit(Request $request, Invitation $invitation, ?string $successRoute = null, array $routeParams = []): RedirectResponse
    {
        $result = $this->processSubmit($request, $invitation);

        $redirect = $successRoute
            ? redirect()->route($successRoute, $routeParams)
            : redirect()->route('ficha.show', $invitation->token);

        return $redirect->with('status', $result['message']);
    }

    /**
     * @return array{message: string, player: Player, invitation: Invitation}
     */
    public function submitForApi(Request $request, Invitation $invitation): array
    {
        return $this->processSubmit($request, $invitation);
    }

    /**
     * @return array{message: string, player: Player, invitation: Invitation}
     */
    private function processSubmit(Request $request, Invitation $invitation): array
    {
        abort_unless($invitation->isUsable(), 410, 'Esta ficha ya no está disponible para editar.');

        $player = $invitation->player()->with(['guardian', 'documents'])->firstOrFail();
        $player->ensureDocuments();

        abort_unless($request->boolean('complete'), 422, 'Tenés que completar y enviar la confirmación.');

        $guardianFirst = trim((string) $request->input('guardian_first_name', ''));
        $guardianLast = trim((string) $request->input('guardian_last_name', ''));
        $guardianFull = trim($guardianFirst.' '.$guardianLast);
        if ($guardianFull !== '') {
            $request->merge(['guardian_name' => $guardianFull]);
        }

        $altName = trim((string) $request->input('guardian_alternate_name', ''));
        $altPhone = trim((string) $request->input('guardian_alternate_phone', ''));
        $request->merge([
            'guardian_alternate_contact' => trim(collect([$altName, $altPhone])->filter()->implode(' · ')),
        ]);

        $data = $request->validate([
            'first_name' => ['required', 'string', 'max:120'],
            'last_name' => ['required', 'string', 'max:120'],
            'guardian_first_name' => ['required', 'string', 'max:120'],
            'guardian_last_name' => ['required', 'string', 'max:120'],
            'guardian_name' => ['required', 'string', 'max:180'],
            'guardian_document_number' => ['required', 'string', 'max:40'],
            'relationship' => ['required', 'string', Rule::in(Guardian::relationshipOptions())],
            'email' => ['required', 'email', 'max:180'],
            'player_email' => ['nullable', 'email', 'max:180'],
            'phone' => ['nullable', 'string', 'max:80'],
            'guardian_alternate_name' => ['required', 'string', 'max:180'],
            'guardian_alternate_phone' => ['required', 'string', 'max:80'],
            'guardian_alternate_contact' => ['required', 'string', 'max:255'],
            'document_number' => ['required', 'string', 'max:40'],
            'birth_date' => ['required', 'date', 'after_or_equal:1990-01-01', 'before_or_equal:today'],
            'nationality' => ['nullable', 'string', Rule::in(array_values(\App\Support\Countries::all()))],
            'address' => ['required', 'string', 'max:255'],
            'kit_size' => ['required', 'string', Rule::in(Player::kitSizes())],
            'jersey_number' => ['nullable', 'integer', 'min:1', 'max:99'],
            'position' => ['nullable', 'string', Rule::in(Player::positions())],
            'preferred_foot' => ['nullable', 'string', Rule::in(Player::preferredFeet())],
            'height' => ['required', 'string', 'max:40'],
            'weight' => ['required', 'string', 'max:40'],
            'blood_type' => ['required', 'string', Rule::in(Player::bloodTypes())],
            'medical_coverage' => ['required', 'string', 'max:120'],
            'allergies' => ['required', 'string', 'max:180'],
            'medication' => ['required', 'string', 'max:180'],
            'illnesses' => ['required', 'string', 'max:180'],
            'restrictions' => ['required', 'string', 'max:180'],
            'emergency_contact' => ['required', 'string', 'max:180'],
            'medical_notes' => ['nullable', 'string', 'max:1200'],
            'vaccination_calendar_complete' => ['required', 'in:0,1'],
            'ongoing_treatment' => ['nullable', 'in:0,1,'],
            'ongoing_treatment_notes' => ['nullable', 'string', 'max:1200'],
            'consent' => ['accepted'],
            'complete' => ['accepted'],
            'auth' => ['required', 'array', 'min:3'],
            'auth.*' => ['string', Rule::in(Player::authorizationDocumentTypes())],
            'document_files' => ['nullable', 'array'],
            'document_files.*' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp,gif', 'max:12288'],
            'photo_data' => ['nullable', 'string', 'max:12000000'],
        ], [
            'document_files.*.mimes' => 'Subí una foto del DNI o del documento (JPG o PNG).',
            'document_files.*.max' => 'Completá todos los campos y volvé a adjuntar las fotos.',
            'document_files.*.uploaded' => 'Completá todos los campos y volvé a adjuntar las fotos.',
            'auth.required' => 'Tenés que aceptar las autorizaciones legales.',
            'auth.min' => 'Tenés que aceptar las tres autorizaciones (responsabilidad, imagen y aptitud).',
            'consent.accepted' => 'Confirmá que los datos son correctos antes de enviar.',
            'email.email' => 'El email del tutor no es válido.',
            'player_email.email' => 'El email del jugador no es válido.',
            'player_email.unique_profile' => 'Ese correo ya está en uso por otro perfil del sistema.',
            'blood_type.required' => 'Elegí el grupo sanguíneo.',
            'height.required' => 'Completá la altura en metros.',
            'weight.required' => 'Completá el peso en Kg.',
            'birth_date.date' => 'La fecha de nacimiento no es válida. Usá día y mes reales (DD/MM/AAAA).',
            'birth_date.after_or_equal' => 'La fecha de nacimiento tiene que ser desde 1990.',
            'birth_date.before_or_equal' => 'La fecha de nacimiento no puede ser futura.',
        ]);

        // El link de ficha se completa aunque el correo ya exista como admin/delegado
        // (no se crea cuenta tutor duplicada; ensureForGuardian lo omite).
        $playerEmail = strtolower(trim((string) ($data['player_email'] ?? '')));
        if ($playerEmail !== '') {
            $playerEmailIssue = app(PlayerAccountService::class)->playerEmailIssue($playerEmail, $player);
            if ($playerEmailIssue) {
                throw ValidationException::withMessages(['player_email' => $playerEmailIssue]);
            }
        }

        foreach (Player::tutorRequiredDocumentTypes() as $type) {
            $document = $player->documentByType($type);
            $incoming = $request->file("document_files.{$type}");
            $hasPhotoData = $type === 'Foto del jugador' && filled($request->input('photo_data'));

            if (! $document?->file_path && ! $incoming instanceof UploadedFile && ! $hasPhotoData) {
                throw ValidationException::withMessages([
                    "document_files.{$type}" => "Adjuntá {$type} para continuar.",
                ]);
            }
        }

        $guardian = Guardian::updateOrCreate(
            ['player_id' => $player->id],
            [
                'name' => $data['guardian_name'],
                'document_number' => $data['guardian_document_number'],
                'relationship' => $data['relationship'],
                'email' => $data['email'],
                'phone' => $data['phone'] ?? $player->guardian?->phone,
                'alternate_contact' => $data['guardian_alternate_contact'],
                'consent_status' => 'approved',
            ]
        );

        $playerPayload = [
            'first_name' => $data['first_name'],
            'last_name' => $data['last_name'],
            'document_number' => $data['document_number'],
            'email' => isset($data['player_email']) ? strtolower(trim((string) $data['player_email'])) ?: null : $player->email,
            'birth_date' => $data['birth_date'],
            'nationality' => $data['nationality'] ?? $player->nationality ?? 'Argentina',
            'address' => $data['address'],
            'kit_size' => $data['kit_size'],
            'jersey_number' => $data['jersey_number'] ?? null,
            'position' => $data['position'] ?? null,
            'preferred_foot' => $data['preferred_foot'] ?? null,
            'height' => $data['height'],
            'weight' => $data['weight'],
            'blood_type' => $data['blood_type'],
            'medical_coverage' => $data['medical_coverage'],
            'allergies' => $data['allergies'],
            'medication' => $data['medication'],
            'illnesses' => $data['illnesses'],
            'restrictions' => $data['restrictions'],
            'emergency_contact' => $data['emergency_contact'],
            'medical_notes' => $data['medical_notes'] ?? null,
            'vaccination_calendar_complete' => $request->boolean('vaccination_calendar_complete'),
        ];

        if ($request->exists('ongoing_treatment')) {
            $value = $request->input('ongoing_treatment');
            $playerPayload['ongoing_treatment'] = $value === '' || $value === null
                ? null
                : $request->boolean('ongoing_treatment');
            $playerPayload['ongoing_treatment_notes'] = $playerPayload['ongoing_treatment'] === true
                ? ($data['ongoing_treatment_notes'] ?? null)
                : null;
        }

        $player->update($playerPayload);

        $this->storeTutorDocuments($request, $player, $guardian->name);
        $this->storeTutorPhoto($request, $player, $guardian->name);

        $authContext = GuardianAuthorizationTexts::context(
            $player->fresh(['team.tournament', 'team.category']),
            $guardian
        );

        $consents = $request->input('auth', []);
        foreach (Player::authorizationDocumentTypes() as $type) {
            if (! in_array($type, $consents, true)) {
                continue;
            }
            $document = $player->documents()->firstOrCreate(['type' => $type], ['status' => 'pending']);
            $document->update([
                'uploaded_by_name' => $guardian->name,
                'uploaded_at' => $document->uploaded_at ?: now(),
                'notes' => GuardianAuthorizationTexts::signedNote($type, $authContext),
                'status' => 'approved',
                'reviewed_at' => now(),
            ]);
        }

        app(GuardianAuthorizationCertificateService::class)->generate(
            $player->fresh(['team.tournament', 'team.category', 'documents']),
            $guardian,
            $invitation
        );

        $invitation->update([
            'name' => $guardian->name,
            'email' => $guardian->email,
            'family_status' => 'completed',
            'status' => 'accepted',
            'accepted_at' => now(),
            'accessed_at' => $invitation->accessed_at ?: now(),
        ]);

        if (in_array($player->status, ['draft', 'awaiting_guardian', 'in_progress', 'pending'], true)) {
            $player->update(['status' => 'submitted']);
        }

        $player = $player->fresh(['team.tournament', 'team.category', 'guardian']);

        app(GuardianAccountService::class)->ensureForGuardian(
            $player,
            $data['email'],
            $guardian->name,
            $invitation->invited_by
        );

        $status = 'Ficha enviada correctamente. Quedó en revisión por el administrador. Ya no podés editarla desde acá; si necesitás cambios, contactá al delegado o administrador.';

        $playerEmail = trim((string) ($player->email ?? ''));
        if ($playerEmail !== '') {
            $playerAccount = app(PlayerAccountService::class)->ensureForPlayer($player, $invitation->invited_by);
            app(PlayerInvitationService::class)->record(
                $player->fresh(),
                $playerEmail,
                $invitation->invited_by,
                sendMail: false,
                account: $playerAccount
            );

            if ($playerAccount) {
                $status .= ' Acceso del jugador habilitado con el correo '.$playerEmail.'.';
                if ($playerAccount['created'] && $playerAccount['password']) {
                    $status .= ' Clave inicial del jugador: '.$playerAccount['password'].' (cambiable en Mi cuenta).';
                }
            } else {
                $status .= ' No se pudo crear el acceso del jugador con '.$playerEmail.' (ese correo ya está en uso por otro perfil). La ficha igual quedó enviada.';
            }
        }

        return [
            'message' => $status,
            'player' => $player->fresh(['team.category.tournament', 'team.delegation', 'guardian', 'documents']),
            'invitation' => $invitation->fresh(),
        ];
    }

    public function markAccessed(Invitation $invitation): Invitation
    {
        if ($invitation->family_status === 'generated') {
            $invitation->update([
                'family_status' => 'accessed',
                'accessed_at' => $invitation->accessed_at ?: now(),
            ]);
        }

        return $invitation->fresh();
    }

    private function storeTutorDocuments(Request $request, Player $player, string $uploadedBy): void
    {
        foreach (Player::tutorUploadDocumentTypes() as $type) {
            if ($type === 'Foto del jugador') {
                continue;
            }

            $file = $request->file("document_files.{$type}");
            if (! $file instanceof UploadedFile) {
                continue;
            }

            $document = $player->documents()->firstOrCreate(
                ['type' => $type],
                ['status' => 'pending']
            );
            $document->storeImage($file, $uploadedBy);
        }
    }

    private function storeTutorPhoto(Request $request, Player $player, string $uploadedBy): void
    {
        $document = $player->documents()->firstOrCreate(
            ['type' => 'Foto del jugador'],
            ['status' => 'pending']
        );

        $payload = ShieldPayload::fromRequest($request, 'document_files.Foto del jugador', 'photo_data');
        if ($payload) {
            $document->storeBinary($payload[0], $payload[1], $uploadedBy, 'foto-jugador.'.$payload[1]);
            $player->update(['photo_path' => $document->fresh()->file_path]);

            return;
        }

        $file = $request->file('document_files.Foto del jugador');
        if ($file instanceof UploadedFile) {
            $document->storeImage($file, $uploadedBy);
            $player->update(['photo_path' => $document->fresh()->file_path]);
        }
    }
}
