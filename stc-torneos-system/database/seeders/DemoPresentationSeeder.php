<?php

namespace Database\Seeders;

use App\Models\AppNotification;
use App\Models\AuditLog;
use App\Models\ContentPost;
use App\Models\FixtureMatch;
use App\Models\Player;
use App\Models\PlayerDocument;
use App\Models\Tournament;
use App\Models\User;
use Illuminate\Database\Seeder;

class DemoPresentationSeeder extends Seeder
{
    public function run(): void
    {
        if (app()->runningUnitTests()) {
            return;
        }

        $admin = User::query()->where('email', 'admin@stctorneos.demo')->first();
        $stc = Tournament::query()->where('slug', 'santa-teresita-cup-2026')->first();

        Tournament::updateOrCreate(
            ['slug' => 'copa-invierno-stc'],
            [
                'name' => 'Copa Invierno STC 2026',
                'edition' => 'Copa Invierno',
                'logo_path' => 'images/stc-logo.png',
                'country' => 'Argentina',
                'city' => 'Mar del Plata',
                'venue_name' => 'Complejo Invierno STC',
                'timezone' => 'America/Argentina/Buenos_Aires',
                'location' => 'Mar del Plata, Buenos Aires',
                'starts_at' => '2026-07-01',
                'ends_at' => '2026-07-05',
                'status' => 'finished',
                'description' => 'Torneo demo adicional para mostrar filtros y múltiples competencias.',
                'contact_name' => 'Mesa Central STC',
                'contact_email' => 'operacion@stctorneos.demo',
                'contact_phone' => '+54 9 11 5555-2026',
                'visibility' => 'public',
                'registration_starts_at' => '2026-04-01',
                'registration_ends_at' => '2026-06-15',
            ]
        );

        $this->call(CopaInviernoDemoSeeder::class);

        if ($stc && $admin) {
            ContentPost::updateOrCreate(
                ['slug' => 'bienvenida-torneo-stc-2026'],
                [
                    'tournament_id' => $stc->id,
                    'author_id' => $admin->id,
                    'type' => 'news',
                    'title' => 'Bienvenida a Santa Teresita Cup 2026',
                    'summary' => 'Arrancó la competencia con 15 categorías, fixture publicado y bandeja documental activa.',
                    'body' => 'Delegados y staff: revisen fichas, documentación y planillas desde el panel Admin Web.',
                    'cover_path' => 'images/category-banner.svg',
                    'audience' => 'public',
                    'status' => 'published',
                    'pinned' => true,
                    'published_at' => now()->subDays(2),
                ]
            );

            AppNotification::updateOrCreate(
                ['title' => 'Fichas pendientes de revisión'],
                [
                    'tournament_id' => $stc->id,
                    'created_by' => $admin->id,
                    'channel' => 'in_app',
                    'body' => 'Hay jugadores con documentación observada o pendiente. Revisá la bandeja documental.',
                    'audience' => 'staff',
                    'status' => 'sent',
                    'sent_at' => now()->subHours(6),
                ]
            );
        }

        foreach (['Mateo', 'Lucas', 'Thiago'] as $firstName) {
            $player = Player::query()->where('first_name', $firstName)->first();
            if (! $player) {
                continue;
            }

            foreach (Player::documentTypes() as $type) {
                PlayerDocument::query()->firstOrCreate(
                    ['player_id' => $player->id, 'type' => $type],
                    ['status' => 'pending']
                );
            }
        }

        PlayerDocument::query()
            ->whereHas('player', fn ($query) => $query->where('first_name', 'Mateo')->where('last_name', 'Díaz'))
            ->where('type', 'Apto médico')
            ->update(['status' => 'pending', 'notes' => 'Pendiente de revisión administrativa.']);

        PlayerDocument::query()
            ->whereHas('player', fn ($query) => $query->where('first_name', 'Lucas')->where('last_name', 'Fernández'))
            ->where('type', 'DNI frente')
            ->update(['status' => 'observed', 'notes' => 'Foto cortada. Pedir nueva toma.']);

        FixtureMatch::query()
            ->where('status', 'live')
            ->whereDate('scheduled_at', '<', now()->toDateString())
            ->update(['scheduled_at' => now()->format('Y-m-d H:i:s')]);

        AuditLog::updateOrCreate(
            ['module' => 'Sistema', 'action' => 'demo_presentation_seed'],
            [
                'user_id' => $admin?->id,
                'description' => 'Datos de presentación demo enriquecidos (Copa Invierno, avisos y bandeja documental).',
                'metadata' => [
                    'tournaments' => Tournament::count(),
                    'players' => Player::count(),
                    'matches' => FixtureMatch::count(),
                    'pending_documents' => PlayerDocument::query()->where('status', 'pending')->count(),
                ],
                'created_at' => now(),
            ]
        );
    }
}
