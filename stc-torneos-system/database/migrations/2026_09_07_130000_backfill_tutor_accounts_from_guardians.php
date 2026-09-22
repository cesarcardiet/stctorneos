<?php

use App\Models\Guardian;
use App\Services\GuardianAccountService;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        $service = app(GuardianAccountService::class);

        Guardian::query()
            ->whereNotNull('email')
            ->where('email', '!=', '')
            ->with(['player.team.tournament'])
            ->orderBy('id')
            ->chunkById(100, function ($guardians) use ($service): void {
                foreach ($guardians as $guardian) {
                    $player = $guardian->player;
                    if (! $player) {
                        continue;
                    }

                    $service->ensureForGuardian(
                        $player,
                        (string) $guardian->email,
                        (string) $guardian->name
                    );
                }
            });
    }

    public function down(): void
    {
        // Keep tutor accounts created from guardian emails.
    }
};
