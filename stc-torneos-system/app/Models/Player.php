<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Player extends Model
{
    protected $fillable = [
        'team_id',
        'first_name',
        'last_name',
        'document_number',
        'email',
        'birth_date',
        'nationality',
        'position',
        'jersey_number',
        'kit_size',
        'photo_path',
        'status',
        'address',
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
        'vaccination_calendar_complete',
        'ongoing_treatment',
        'ongoing_treatment_notes',
        'notes',
        'observation_reason',
    ];

    protected function casts(): array
    {
        return [
            'birth_date' => 'date',
            'vaccination_calendar_complete' => 'boolean',
            'ongoing_treatment' => 'boolean',
        ];
    }

    public function age(): ?int
    {
        return $this->birth_date?->age;
    }

    public function vaccinationCalendarLabel(): string
    {
        if ($this->vaccination_calendar_complete === null) {
            return 'Sin cargar';
        }

        return $this->vaccination_calendar_complete ? 'Sí' : 'No';
    }

    public function ongoingTreatmentLabel(): string
    {
        if ($this->ongoing_treatment === null) {
            return 'Sin cargar';
        }

        return $this->ongoing_treatment ? 'Sí' : 'No';
    }

    /**
     * @return array<string, string>
     */
    public static function statusLabels(): array
    {
        return [
            'draft' => 'Registro inicial',
            'awaiting_guardian' => 'Pendiente de Tutor',
            'in_progress' => 'En proceso',
            'submitted' => 'Esperando aprobación',
            'pending' => 'Esperando aprobación',
            'observed' => 'Observado',
            'approved' => 'Aprobado',
            'rejected' => 'Rechazado',
            'enabled' => 'Habilitado',
            'blocked' => 'Bloqueado',
        ];
    }

    /**
     * @return list<string>
     */
    public static function waitingInscriptionStatuses(): array
    {
        return ['draft', 'awaiting_guardian', 'in_progress', 'submitted', 'pending', 'observed'];
    }

    public function isWaitingInscription(): bool
    {
        return in_array($this->status, self::waitingInscriptionStatuses(), true);
    }

    public function inscriptionBandLabel(): string
    {
        return match ($this->status) {
            'rejected' => 'Rechazado',
            default => $this->isWaitingInscription() ? 'Esperando aprobación' : $this->statusLabel(),
        };
    }

    public function statusLabel(): string
    {
        return self::statusLabels()[$this->status] ?? ucfirst((string) $this->status);
    }

    /**
     * @return list<string>
     */
    public static function kitSizes(): array
    {
        return collect(self::kitSizeGroups())->flatten()->values()->all();
    }

    /**
     * @return array<string, list<string>>
     */
    public static function kitSizeGroups(): array
    {
        return [
            'Numérica' => ['6', '8', '10', '12', '14', '16'],
            'Letras' => ['S', 'M', 'L', 'XL', 'XXL'],
        ];
    }

    /**
     * @return list<string>
     */
    public static function positions(): array
    {
        return ['Arquero', 'Defensor', 'Mediocampista', 'Delantero'];
    }

    /**
     * @return list<string>
     */
    public static function preferredFeet(): array
    {
        return ['Derecha', 'Izquierda', 'Ambidiestro'];
    }

    public function kitSizeLabel(): string
    {
        return filled($this->kit_size) ? (string) $this->kit_size : 'Sin talle';
    }

    public function fileStatusLabel(): string
    {
        return $this->statusLabel();
    }

    public function layerOneLabel(): string
    {
        $complete = filled($this->first_name)
            && filled($this->last_name)
            && $this->team_id
            && $this->team?->category_id;

        return $complete ? 'Completa' : 'Pendiente';
    }

    public function guardianConfirmationSigned(): bool
    {
        if ($this->guardianCertificate()?->fileUrl()) {
            return true;
        }

        if ($this->authorizationSummary() === 'Completas') {
            return true;
        }

        return $this->guardian?->isApproved() ?? false;
    }

    public function layerTwoLabel(): string
    {
        if ($this->guardianConfirmationSigned()) {
            return 'Firmado';
        }

        if ($this->status === 'awaiting_guardian') {
            return 'Pendiente de tutor';
        }

        $invitation = $this->latestGuardianInvitation();
        if ($invitation && in_array($invitation->family_status, ['generated', 'accessed', 'in_progress'], true)) {
            return 'Enlace enviado';
        }

        return filled($this->guardian?->name) ? 'Sin confirmar' : 'Pendiente de tutor';
    }

    public function reviewStatusLabel(): string
    {
        return match ($this->status) {
            'enabled', 'approved' => 'Aprobado',
            'observed' => 'Observado',
            'rejected', 'blocked' => 'Rechazado',
            'submitted', 'pending' => 'En revisión',
            default => $this->statusLabel(),
        };
    }

    public function authorizationSummary(): string
    {
        $parts = collect(self::authorizationDocumentTypes())
            ->map(fn (string $type) => $this->hasApprovedDocument($type));

        if ($parts->every(fn ($ok) => $ok)) {
            return 'Completas';
        }

        if ($parts->contains(true)) {
            return 'Parcial';
        }

        return 'Pendientes';
    }

    public function photoIsAuthorized(): bool
    {
        return $this->hasApprovedDocument('Uso de imagen');
    }

    public function listPhotoUrl(): string
    {
        return $this->photoUrl();
    }

    public function hasStoredPhoto(): bool
    {
        return $this->storedPhotoPath() !== null;
    }

    public function whatsappShareUrl(): ?string
    {
        $name = $this->guardian?->name;
        $team = $this->team?->name;
        $text = 'Hola'.($name ? ' '.$name : '').', te comparto el enlace de confirmación legal de '.$this->fullName();
        if ($team) {
            $text .= ' ('.$team.')';
        }
        $text .= ' en STC Torneos.';

        $invitation = $this->latestGuardianInvitation();
        if ($invitation && ($invitation->isUsable() || $invitation->canViewFicha())) {
            $text .= ' '.$invitation->publicUrl();
        }

        return \App\Support\WhatsApp::url($this->guardian?->phone, $text);
    }

    public function fullName(): string
    {
        return trim($this->first_name.' '.$this->last_name);
    }

    public function photoUrl(): string
    {
        $path = $this->storedPhotoPath() ?: self::defaultPhotoPath();

        if (! str_starts_with($path, 'http') && ! is_file(public_path($path))) {
            $path = self::defaultPhotoPath();
        }

        if (str_starts_with($path, 'http')) {
            return $path;
        }

        $url = asset($path);
        $mtime = @filemtime(public_path($path));
        if ($mtime) {
            $url .= (str_contains($url, '?') ? '&' : '?').'v='.$mtime;
        }

        return $url;
    }

    public function storedPhotoPath(): ?string
    {
        $fromDoc = $this->relationLoaded('documents')
            ? $this->documentByType('Foto del jugador')?->file_path
            : null;

        // photo_path first so a fresh upload is never shadowed by a stale document row.
        foreach ([$this->photo_path, $fromDoc] as $path) {
            if (self::photoPathIsStored($path)) {
                return $path;
            }
        }

        return null;
    }

    public static function defaultPhotoPath(): string
    {
        return is_file(public_path('images/defaults/player.svg'))
            ? 'images/defaults/player.svg'
            : 'images/stc-logo.png';
    }

    public static function photoPathIsStored(?string $path): bool
    {
        $path = trim((string) $path);
        if ($path === '' || in_array($path, [
            'images/players/thiago-martinez.png',
            'images/stc-logo.png',
            'images/defaults/player.svg',
            'images/defaults/person.svg',
        ], true)) {
            return false;
        }

        if (str_starts_with($path, 'http')) {
            return true;
        }

        return is_file(public_path($path));
    }

    public function formattedDocument(): string
    {
        $digits = preg_replace('/\D+/', '', (string) $this->document_number);

        if (! $digits) {
            return 'Sin documento';
        }

        return number_format((int) $digits, 0, ',', '.');
    }

    public function documentationSummary(): string
    {
        $this->loadMissing('documents');
        $current = collect(self::documentTypes())->map(fn (string $type) => $this->documentByType($type))->filter();

        if ($current->contains(fn ($document) => $document->status === 'observed')) {
            return 'Observada';
        }

        if ($current->contains(fn ($document) => $document->status === 'rejected')) {
            return 'Rechazada';
        }

        if ($current->count() === count(self::documentTypes()) && $current->every(fn ($document) => $document->status === 'approved')) {
            return 'Completa';
        }

        return 'Incompleta';
    }

    public function eligibilityLabel(): string
    {
        return $this->status === 'enabled' ? 'Habilitado' : 'No habilitado';
    }

    public function guardianConsentLabel(): string
    {
        return $this->guardian?->consentLabel() ?? 'Sin tutor';
    }

    public function canReviewOnRoster(): bool
    {
        return ! in_array($this->status, ['enabled', 'blocked'], true);
    }

    public function isFileComplete(): bool
    {
        return in_array($this->documentationSummary(), ['Completa'], true);
    }

    public function documentLabel(string $type): string
    {
        $document = $this->documentByType($type);

        if ($document?->wasSignedByGuardian()) {
            return $type === self::guardianCertificateType()
                ? 'Generada'
                : 'Firmado por tutor';
        }

        $status = $this->documentStatus($type);

        return match ($status) {
            'approved' => $type === 'Autorización parental' ? 'Firmada' : ($type === 'Ficha médica' ? 'Aprobada' : 'Aprobado'),
            'observed' => 'Observado',
            'rejected' => 'Rechazado',
            default => 'Pendiente',
        };
    }

    /**
     * @return list<string>
     */
    public static function documentTypes(): array
    {
        return array_merge(self::uploadDocumentTypes(), self::authorizationDocumentTypes());
    }

    /**
     * @return list<string>
     */
    public static function uploadDocumentTypes(): array
    {
        return [
            'DNI frente',
            'DNI dorso',
            'Cobertura médica',
            'Foto del jugador',
        ];
    }

    /**
     * Documentos que el tutor puede adjuntar en la ficha pública.
     *
     * @return list<string>
     */
    public static function tutorUploadDocumentTypes(): array
    {
        return [
            'DNI frente',
            'DNI dorso',
            'Foto del jugador',
            'Cobertura médica',
        ];
    }

    /**
     * Documentos obligatorios en la ficha pública del tutor.
     *
     * @return list<string>
     */
    public static function tutorRequiredDocumentTypes(): array
    {
        return [
            'DNI frente',
            'DNI dorso',
            'Foto del jugador',
        ];
    }

    /**
     * @return list<string>
     */
    public static function authorizationDocumentTypes(): array
    {
        return [
            'Autorización',
            'Uso de imagen',
            'Apto médico',
        ];
    }

    /**
     * @return list<string>
     */
    public static function bloodTypes(): array
    {
        return ['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-'];
    }

    public static function guardianCertificateType(): string
    {
        return 'Constancia de autorización del tutor';
    }

    /**
     * @return list<string>
     */
    public static function managedDocumentTypes(): array
    {
        return array_merge(self::documentTypes(), [self::guardianCertificateType()]);
    }

    /**
     * @return list<string>
     */
    public static function hiddenDocumentTypes(): array
    {
        return [
            'Reglamento',
            'Declaraciones',
            'Atención médica',
        ];
    }

    public function isUploadDocument(string $type): bool
    {
        return in_array($type, self::uploadDocumentTypes(), true);
    }

    public function ensureDocuments(): void
    {
        foreach (self::managedDocumentTypes() as $type) {
            $this->documents()->firstOrCreate(['type' => $type], ['status' => 'pending']);
        }

        $this->unsetRelation('documents');
        $this->load('documents');

        $migrate = [
            'DNI' => 'DNI frente',
            'DNI / documento' => 'DNI frente',
            'Aut. participación' => 'Autorización',
            'Autorización parental' => 'Autorización',
            'Ficha médica' => 'Apto médico',
        ];

        foreach ($migrate as $from => $to) {
            $source = $this->documents->firstWhere('type', $from);
            $target = $this->documents->firstWhere('type', $to);
            if (! $source || ! $target) {
                continue;
            }
            if (! $target->file_path && $source->file_path) {
                $target->fill([
                    'file_path' => $source->file_path,
                    'original_name' => $source->original_name,
                    'uploaded_by_name' => $source->uploaded_by_name,
                    'uploaded_at' => $source->uploaded_at,
                ]);
            }
            if ($target->status === 'pending' && $source->status !== 'pending') {
                $target->status = $source->status;
            }
            if ($target->isDirty()) {
                $target->save();
            }
        }

        $photo = $this->documents->firstWhere('type', 'Foto del jugador');
        if ($photo && ! $photo->file_path && filled($this->photo_path)) {
            $photo->update(['file_path' => $this->photo_path]);
        }

        $this->unsetRelation('documents');
        $this->load('documents');
    }

    public function documentByType(string $type): ?PlayerDocument
    {
        $this->loadMissing('documents');

        $aliases = [
            'DNI frente' => ['DNI frente', 'DNI', 'DNI / documento'],
            'DNI dorso' => ['DNI dorso'],
            'Cobertura médica' => ['Cobertura médica'],
            'Foto del jugador' => ['Foto del jugador'],
            'Autorización' => ['Autorización', 'Aut. participación', 'Autorización parental'],
            'Uso de imagen' => ['Uso de imagen'],
            'Apto médico' => ['Apto médico', 'Ficha médica'],
            'DNI / documento' => ['DNI frente', 'DNI dorso', 'DNI', 'DNI / documento'],
            'Ficha médica' => ['Apto médico', 'Ficha médica'],
            'Autorización parental' => ['Autorización', 'Aut. participación', 'Autorización parental'],
        ];

        $types = $aliases[$type] ?? [$type];

        return $this->documents->first(fn (PlayerDocument $document) => in_array($document->type, $types, true));
    }

    public function hasApprovedDocument(string $type): bool
    {
        return $this->documentStatus($type) === 'approved';
    }

    public function documentStatus(string $type): string
    {
        return $this->documentByType($type)?->status ?? 'pending';
    }

    public function documentFile(string $type): ?string
    {
        return $this->documentByType($type)?->file_path;
    }

    public function guardianCertificate(): ?PlayerDocument
    {
        return $this->documentByType(self::guardianCertificateType());
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function guardian(): HasOne
    {
        return $this->hasOne(Guardian::class);
    }

    public function documents(): HasMany
    {
        return $this->hasMany(PlayerDocument::class);
    }

    public function invitations(): HasMany
    {
        return $this->hasMany(Invitation::class)->where('kind', 'guardian')->latest('id');
    }

    public function latestGuardianInvitation(): ?Invitation
    {
        $invitations = $this->relationLoaded('invitations')
            ? $this->invitations->sortByDesc('id')->values()
            : $this->invitations()->orderByDesc('id')->get();

        return $invitations->first(fn (Invitation $invitation) => $invitation->isUsable())
            ?: $invitations->first();
    }

    public function tutorFichaLocked(): bool
    {
        return $this->tutorFichaInvitation()?->family_status === 'completed';
    }

    public function tutorFichaInvitation(): ?Invitation
    {
        $invitations = $this->relationLoaded('invitations')
            ? $this->invitations->sortByDesc('id')->values()
            : $this->invitations()->orderByDesc('id')->get();

        $completed = $invitations->first(fn (Invitation $invitation) => $invitation->family_status === 'completed');
        if ($completed) {
            return $completed;
        }

        return $invitations->first(fn (Invitation $invitation) => $invitation->isUsable());
    }
}
