<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ForgotPasswordRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Password;
use Illuminate\View\View;

class PasswordResetLinkController extends Controller
{
    private const NEUTRAL_STATUS = 'Si el correo corresponde a una cuenta habilitada, recibirás un enlace con los siguientes pasos.';

    public function create(): View
    {
        return view('auth.forgot-password');
    }

    public function store(ForgotPasswordRequest $request): RedirectResponse
    {
        if (! $request->isRateLimited()) {
            $request->incrementRateLimiter();

            Password::sendResetLink([
                'email' => (string) $request->validated('email'),
                'is_active' => true,
            ]);
        }

        return back()
            ->withInput($request->only('email'))
            ->with('status', self::NEUTRAL_STATUS);
    }
}
