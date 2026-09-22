<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\User;
use App\Services\Auth\InvitationRegistrationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class AccessController extends Controller
{
    public function __construct(
        private readonly InvitationRegistrationService $registrationService,
    ) {}

    public function showRegister(): View|RedirectResponse
    {
        if (auth()->check()) {
            return redirect()->route('workspace.home');
        }

        return view('auth.register');
    }

    public function register(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:190'],
            'invitation_code' => ['required', 'string', 'max:120'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        try {
            $user = $this->registrationService->accept($data);
        } catch (ValidationException $exception) {
            return redirect()
                ->route('register')
                ->withErrors($exception->errors())
                ->withInput($request->only('name', 'email', 'invitation_code'));
        }

        return redirect()
            ->route('login')
            ->with('status', 'Cuenta activada para '.$user->email.'. Ya podés ingresar con la contraseña que definiste.');
    }

    public function showForgotPassword(): View|RedirectResponse
    {
        if (auth()->check()) {
            return redirect()->route('workspace.home');
        }

        return view('auth.forgot-password');
    }

    public function sendForgotPassword(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email', 'max:190'],
        ]);

        $email = Str::lower(trim($data['email']));
        $user = User::query()->whereRaw('LOWER(TRIM(email)) = ?', [$email])->first();

        if ($user) {
            $token = Str::random(64);
            DB::table('password_reset_tokens')->updateOrInsert(
                ['email' => $user->email],
                ['token' => Hash::make($token), 'created_at' => now()]
            );

            AuditLog::create([
                'user_id' => $user->id,
                'module' => 'Acceso',
                'action' => 'password_reset_requested',
                'description' => 'Solicitud de recuperación de clave.',
                'metadata' => ['email' => $user->email],
            ]);
        }

        return redirect()
            ->route('password.request')
            ->with(
                'status',
                'Si el correo está registrado, recibirás un enlace seguro para restablecer tu contraseña.'
            );
    }
}
