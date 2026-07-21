<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

class ForgotPasswordRequest extends FormRequest
{
    private const MAX_ATTEMPTS_PER_IDENTITY = 5;

    private const MAX_ATTEMPTS_PER_IP = 20;

    private const DECAY_SECONDS = 60;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email', 'max:255'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'email' => Str::lower(trim((string) $this->input('email'))),
        ]);
    }

    public function isRateLimited(): bool
    {
        return RateLimiter::tooManyAttempts($this->throttleKey(), self::MAX_ATTEMPTS_PER_IDENTITY)
            || RateLimiter::tooManyAttempts($this->ipThrottleKey(), self::MAX_ATTEMPTS_PER_IP);
    }

    public function incrementRateLimiter(): void
    {
        RateLimiter::hit($this->throttleKey(), self::DECAY_SECONDS);
        RateLimiter::hit($this->ipThrottleKey(), self::DECAY_SECONDS);
    }

    public function throttleKey(): string
    {
        return hash('sha256', 'password-reset|'.Str::lower($this->string('email')->toString()).'|'.$this->ip());
    }

    private function ipThrottleKey(): string
    {
        return hash('sha256', 'password-reset-ip|'.$this->ip());
    }
}
