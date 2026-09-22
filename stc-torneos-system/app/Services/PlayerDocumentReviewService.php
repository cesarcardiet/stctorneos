<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Player;
use App\Models\PlayerDocument;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class PlayerDocumentReviewService
{
    public function update(PlayerDocument $document, array $data, ?User $user = null): PlayerDocument
    {
        abort_if(
            $document->wasSignedByGuardian() && ($data['status'] ?? $document->status) !== $document->status,
            422,
            'Esta autorización ya fue firmada por el tutor y no requiere revisión manual.'
        );

        if (in_array($data['status'] ?? '', ['observed', 'rejected'], true) && empty(trim((string) ($data['notes'] ?? '')))) {
            throw ValidationException::withMessages([
                'notes' => 'Indicá el motivo de la observación o el rechazo.',
            ]);
        }

        $document->update([
            'status' => $data['status'],
            'notes' => $data['notes'] ?? null,
            'checklist' => $data['checklist'] ?? $document->checklistStatus(),
            'reviewed_at' => now(),
            'expires_at' => $data['expires_at'] ?? $document->expires_at,
        ]);

        $this->syncPlayerEligibility($document);
        $this->audit($document, 'review', 'Revisión documental: '.$document->type.' → '.$document->statusLabel(), $user);

        return $document->fresh();
    }

    public function quickStatus(PlayerDocument $document, string $status, ?string $notes = null, ?User $user = null): PlayerDocument
    {
        return $this->update($document, [
            'status' => $status,
            'notes' => $notes,
        ], $user);
    }

    public function enablePlayer(Player $player, ?User $user = null, string $module = 'Jugadores'): Player
    {
        $player->loadMissing('documents');

        if ($player->documentationSummary() !== 'Completa') {
            throw ValidationException::withMessages([
                'player' => 'No se puede habilitar: la documentación todavía no está completa.',
            ]);
        }

        $player->update([
            'status' => 'enabled',
            'observation_reason' => null,
        ]);

        AuditLog::create([
            'user_id' => $user?->id ?? auth()->id(),
            'module' => $module,
            'action' => 'enable_player',
            'description' => 'Jugador habilitado para jugar: '.$player->fullName().'.',
            'auditable_type' => Player::class,
            'auditable_id' => $player->id,
            'metadata' => ['player_id' => $player->id],
        ]);

        return $player->fresh();
    }

    /**
     * Aprueba de una vez todos los documentos del jugador que el club debe revisar
     * y que ya tienen archivo cargado (no toca firmas del tutor).
     *
     * @return array{approved: int, skipped: int}
     */
    public function approveAllForPlayer(Player $player, ?User $user = null): array
    {
        $player->loadMissing('documents');
        $approved = 0;
        $skipped = 0;

        foreach (Player::documentTypes() as $type) {
            $document = $player->documentByType($type);

            if (! $document || ! $document->fileUrl()) {
                $skipped++;

                continue;
            }

            if ($document->wasSignedByGuardian()) {
                $skipped++;

                continue;
            }

            if (! PlayerDocument::requiresClubReviewForType($type)
                && in_array($type, Player::authorizationDocumentTypes(), true)) {
                $skipped++;

                continue;
            }

            if ($document->status === 'approved') {
                $skipped++;

                continue;
            }

            $this->quickStatus($document, 'approved', null, $user);
            $approved++;
        }

        if ($approved > 0) {
            AuditLog::create([
                'user_id' => $user?->id ?? auth()->id(),
                'module' => 'Documentación',
                'action' => 'approve_all_documents',
                'description' => 'Aprobación documental global de '.$player->fullName().': '.$approved.' documento(s).',
                'auditable_type' => Player::class,
                'auditable_id' => $player->id,
                'metadata' => ['approved' => $approved, 'skipped' => $skipped],
            ]);
        }

        return ['approved' => $approved, 'skipped' => $skipped];
    }

    public function validatedReviewPayload(Request $request, bool $requireNotesOnNegative = true): array
    {
        $data = $request->validate([
            'status' => ['required', 'string', 'in:pending,observed,rejected,approved'],
            'notes' => ['nullable', 'string', 'max:1200'],
            'checklist' => ['nullable', 'array'],
            'checklist.*' => ['in:ok,warn,fail'],
            'expires_at' => ['nullable', 'date', 'after_or_equal:1900-01-01', 'before_or_equal:2100-12-31'],
        ]);

        if ($requireNotesOnNegative && in_array($data['status'], ['observed', 'rejected'], true)) {
            $request->validate(['notes' => ['required', 'string', 'max:1200']]);
        }

        return $data;
    }

    private function syncPlayerEligibility(PlayerDocument $document): void
    {
        $player = $document->player;

        if (! $player) {
            return;
        }

        if (in_array($document->status, ['observed', 'rejected'], true) && $player->status === 'enabled') {
            $player->update([
                'status' => 'observed',
                'observation_reason' => $document->notes ?: ($document->type.' observado o rechazado.'),
            ]);
        }
    }

    private function audit(PlayerDocument $document, string $action, string $description, ?User $user = null): void
    {
        AuditLog::create([
            'user_id' => $user?->id ?? auth()->id(),
            'module' => 'Documentación',
            'action' => $action,
            'description' => $description,
            'auditable_type' => PlayerDocument::class,
            'auditable_id' => $document->id,
            'metadata' => [
                'player_id' => $document->player_id,
                'type' => $document->type,
                'status' => $document->status,
            ],
        ]);
    }
}
