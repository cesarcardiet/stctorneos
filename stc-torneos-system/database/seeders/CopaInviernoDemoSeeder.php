<?php

namespace Database\Seeders;

use App\Models\Tournament;
use App\Services\WorkspaceCategorySetup;
use Illuminate\Database\Seeder;

class CopaInviernoDemoSeeder extends Seeder
{
    public function run(): void
    {
        $tournament = Tournament::query()->where('slug', 'copa-invierno-stc')->first();
        if (! $tournament) {
            return;
        }

        if ($tournament->categories()->where('name', 'Categoria 2005/2006')->exists()) {
            return;
        }

        app(WorkspaceCategorySetup::class)->create($tournament, [
            'name' => 'Categoria 2005/2006',
            'birth_year' => '2005/2006',
            'branch' => 'Masculina',
            'modality' => 'Fútbol 11',
            'competition_format' => 'Grupos y finales',
            'description' => 'Categoría de prueba de Copa Invierno, lista para operar.',
            'rules' => 'Todos contra todos en grupos. Clasifican los dos primeros.',
            'groups_count' => 2,
            'points_win' => 3,
            'points_draw' => 1,
            'points_loss' => 0,
            'prize_first' => 'Campeón',
            'prize_second' => 'Subcampeón',
            'prize_third' => 'Tercer puesto',
            'prize_other' => 'Fair Play',
            'teams' => WorkspaceCategorySetup::demoTeams(),
        ]);
    }
}
