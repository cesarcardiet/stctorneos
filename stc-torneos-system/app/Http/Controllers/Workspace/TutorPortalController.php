<?php



namespace App\Http\Controllers\Workspace;



use App\Http\Controllers\Controller;

use App\Models\Player;

use App\Models\User;

use App\Services\GuardianFichaService;

use App\Services\GuardianInvitationService;

use App\Support\TutorCredentials;

use App\Support\WorkspaceAccess;

use Illuminate\Http\RedirectResponse;

use Illuminate\Http\Request;

use Illuminate\View\View;



class TutorPortalController extends Controller

{

    public function home(Request $request): View

    {

        /** @var User $user */

        $user = $request->user();



        return view('workspace.tutor-portal.home', [

            'user' => $user,

            'players' => $user->tutorPlayers(),

            'defaultPasswordHint' => TutorCredentials::defaultPassword(),

            'title' => 'Mis jugadores | STC Torneos',

            'heading' => 'Mis jugadores',

            'subheading' => 'Completá o revisá la ficha de cada jugador a tu cargo.',

            'active' => 'Mis jugadores',

            'access' => WorkspaceAccess::for($user),

        ]);

    }



    public function ficha(Request $request, Player $player): View

    {

        /** @var User $user */

        $user = $request->user();

        $player = $this->assertTutorPlayer($user, $player);

        $player->load(['team.category', 'team.tournament', 'guardian', 'documents', 'invitations']);

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



        $guardian = $player->guardian ?? new \App\Models\Guardian(['relationship' => 'Madre', 'email' => $user->email]);



        return view('workspace.tutor-portal.ficha', [

            'user' => $user,

            'player' => $player->fresh(['team.category', 'team.tournament', 'guardian', 'documents', 'invitations']),

            'invitation' => $invitation,

            'guardian' => $guardian,

            'locked' => $locked,

            'fichaSubmitUrl' => route('workspace.tutor.ficha.update', $player),

            'title' => 'Ficha · '.$player->fullName().' | STC Torneos',

            'heading' => $player->fullName(),

            'subheading' => ($player->team?->name ?? 'Equipo').' · '.($player->team?->category?->name ?? 'Categoría'),

            'active' => 'Mis jugadores',

            'access' => WorkspaceAccess::for($user),

        ]);

    }



    public function updateFicha(Request $request, Player $player): RedirectResponse

    {

        /** @var User $user */

        $user = $request->user();

        $player = $this->assertTutorPlayer($user, $player);

        $player->load('invitations');



        if ($player->tutorFichaLocked()) {

            return redirect()

                ->route('workspace.tutor.ficha', $player)

                ->withErrors([

                    'ficha' => 'Esta ficha ya fue enviada y está en revisión. Si necesitás cambiar algo, pedile al administrador o delegado del torneo.',

                ]);

        }



        $invitation = $player->tutorFichaInvitation()

            ?? app(GuardianInvitationService::class)->ensureInvitation(

                $player,

                $user->email,

                $user->id,

                sendMail: false

            );



        return app(GuardianFichaService::class)->submit(

            $request,

            $invitation,

            'workspace.tutor.ficha',

            ['player' => $player->id]

        );

    }



    private function assertTutorPlayer(User $user, Player $player): Player

    {

        $owned = $user->tutorPlayers()->contains(fn (Player $entry) => (int) $entry->id === (int) $player->id);

        abort_unless($owned, 403, 'Este jugador no está vinculado a tu cuenta de tutor.');



        return $player;

    }

}


