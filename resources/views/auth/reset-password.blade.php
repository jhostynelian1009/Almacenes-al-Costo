@extends('layouts.guest')

@section('title', 'Restablecer contraseña | Almacenes al Costo')
@section('description', 'Define una nueva contraseña para tu cuenta interna.')

@section('content')
    <section class="auth-section" aria-labelledby="reset-password-title">
        <div class="container px-3">
            <div class="card auth-card mx-auto">
                <div class="card-body p-4 p-md-5">
                    <p class="fw-bold mb-2">Almacenes al Costo</p>
                    <h1 class="h2" id="reset-password-title">Restablecer contraseña</h1>
                    <p class="text-body-secondary">La nueva contraseña debe tener al menos 12 caracteres.</p>

                    <form method="POST" action="{{ route('password.update') }}" novalidate>
                        @csrf
                        <input name="token" type="hidden" value="{{ $token }}">

                        <div class="mb-3">
                            <label class="form-label" for="email">Correo electrónico</label>
                            <input class="form-control @error('email') is-invalid @enderror" id="email" name="email" type="email" value="{{ old('email', $email) }}" autocomplete="email" required autofocus @error('email') aria-invalid="true" aria-describedby="email-error" @enderror>
                            @error('email')
                                <div class="invalid-feedback" id="email-error">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="mb-3">
                            <label class="form-label" for="password">Nueva contraseña</label>
                            <input class="form-control @error('password') is-invalid @enderror" id="password" name="password" type="password" autocomplete="new-password" required @error('password') aria-invalid="true" aria-describedby="password-error" @enderror>
                            @error('password')
                                <div class="invalid-feedback" id="password-error">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="mb-4">
                            <label class="form-label" for="password_confirmation">Confirmar nueva contraseña</label>
                            <input class="form-control @error('password_confirmation') is-invalid @enderror" id="password_confirmation" name="password_confirmation" type="password" autocomplete="new-password" required @error('password_confirmation') aria-invalid="true" aria-describedby="password-confirmation-error" @enderror>
                            @error('password_confirmation')
                                <div class="invalid-feedback" id="password-confirmation-error">{{ $message }}</div>
                            @enderror
                        </div>

                        <button class="btn btn-brand w-100" type="submit">Restablecer contraseña</button>
                    </form>

                    <a class="d-inline-block mt-4" href="{{ route('login') }}">Volver al inicio de sesión</a>
                </div>
            </div>
        </div>
    </section>
@endsection
