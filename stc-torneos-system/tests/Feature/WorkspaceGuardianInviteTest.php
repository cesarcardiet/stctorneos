<?php



namespace Tests\Feature;



use App\Models\Category;

use App\Models\Guardian;

use App\Models\Invitation;

use App\Models\Player;

use App\Models\User;

use Database\Seeders\DatabaseSeeder;

use Illuminate\Foundation\Testing\RefreshDatabase;

use Illuminate\Support\Facades\Mail;

use Tests\TestCase;



class WorkspaceGuardianInviteTest extends TestCase

{

    use RefreshDatabase;



    public function test_workspace_player_page_can_generate_guardian_ficha_link(): void

    {

        $this->seed(DatabaseSeeder::class);

        Mail::fake();



        $admin = User::where('email', 'admin@stctorneos.demo')->firstOrFail();

        $player = Player::where('first_name', 'Mateo')->where('last_name', 'Díaz')->firstOrFail();

        $category = Category::query()->whereKey($player->team?->category_id)->firstOrFail();



        $this->actingAs($admin)

            ->get(route('workspace.categories.players.edit', [$category, $player]))

            ->assertOk()

            ->assertSee('Confirmación del tutor')

            ->assertSee('Generar enlace');



        $this->actingAs($admin)

            ->post(route('workspace.categories.players.invite', [$category, $player]), [

                'email' => 'tutor.workspace@stc.test',

            ])

            ->assertRedirect(route('workspace.categories.players.show', [$category, $player]).'#confirmacion-tutor');



        $invitation = Invitation::query()

            ->where('player_id', $player->id)

            ->where('kind', 'guardian')

            ->latest('id')

            ->firstOrFail();



        $this->assertSame('tutor.workspace@stc.test', $invitation->email);

        $this->assertSame('generated', $invitation->family_status);



        $this->assertDatabaseHas('guardians', [

            'player_id' => $player->id,

            'email' => 'tutor.workspace@stc.test',

        ]);



        $this->actingAs($admin)

            ->get(route('workspace.categories.players.show', [$category, $player]))

            ->assertOk()

            ->assertSee('Enlace de confirmación')

            ->assertSee($invitation->publicUrl())

            ->assertSee('Copiar link');



        $this->get($invitation->publicUrl())

            ->assertOk()

            ->assertSee($player->fullName())

            ->assertSee('D.N.I. del tutor')

            ->assertSee('Siguiente');

    }



    public function test_workspace_update_player_creates_guardian_with_document_number(): void

    {

        $this->seed(DatabaseSeeder::class);



        $admin = User::where('email', 'admin@stctorneos.demo')->firstOrFail();

        $player = Player::where('first_name', 'Mateo')->where('last_name', 'Díaz')->firstOrFail();

        $category = Category::query()->whereKey($player->team?->category_id)->firstOrFail();



        Guardian::query()->where('player_id', $player->id)->delete();



        $this->actingAs($admin)

            ->patch(route('workspace.categories.players.update', [$category, $player]), [

                'first_name' => $player->first_name,

                'last_name' => $player->last_name,

                'status' => $player->status,

                'guardian_email' => 'padre@stc.test',

                'guardian_name' => 'Carlos Martínez',

                'guardian_document_number' => '27123456',

                'guardian_relationship' => 'Padre',

            ])

            ->assertRedirect();



        $this->assertDatabaseHas('guardians', [

            'player_id' => $player->id,

            'name' => 'Carlos Martínez',

            'document_number' => '27123456',

            'email' => 'padre@stc.test',

        ]);

    }

}

