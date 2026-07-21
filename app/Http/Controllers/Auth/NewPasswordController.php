<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ResetPasswordRequest;
use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\View\View;

class NewPasswordController extends Controller
{
    private const INVALID_RESET = 'No fue posible restablecer la contraseña. Solicita un nuevo enlace e inténtalo otra vez.';

    public function create(Request $request, string $token): View
    {
        return view('auth.reset-password', [
            'token' => $token,
            'email' => (string) $request->query('email', ''),
        ]);
    }

    public function store(ResetPasswordRequest $request): RedirectResponse
    {
        $status = Password::reset([
            'email' => (string) $request->validated('email'),
            'password' => (string) $request->validated('password'),
            'password_confirmation' => (string) $request->validated('password_confirmation'),
            'token' => (string) $request->validated('token'),
            'is_active' => true,
        ], function (User $user, string $password): void {
            $user->forceFill([
                'password' => Hash::make($password),
                'remember_token' => Str::random(60),
            ])->save();

            event(new PasswordReset($user));
        });

        if ($status !== Password::PASSWORD_RESET) {
            return back()
                ->withInput($request->only('email'))
                ->withErrors(['email' => self::INVALID_RESET]);
        }

        return to_route('login')->with('status', 'Tu contraseña fue restablecida correctamente. Ya puedes iniciar sesión.');
    }
}
