@extends('layouts.guest')

@section('title', 'Recuperar contraseña | Almacenes al Costo')
@section('description', 'Solicita un enlace seguro para restablecer tu contraseña.')

@section('content')
    <section class="auth-section" aria-labelledby="forgot-password-title">
        <div class="container px-3">
            <div class="card auth-card mx-auto">
                <div class="card-body p-4 p-md-5">
                    <p class="fw-bold mb-2">Almacenes al Costo</p>
                    <h1 class="h2" id="forgot-password-title">Recuperar contraseña</h1>
                    <p class="text-body-secondary">Ingresa tu correo interno y te enviaremos los siguientes pasos si la cuenta está habilitada.</p>

                    <form method="POST" action="{{ route('password.email') }}" novalidate>
                        @csrf

                        <div class="mb-4">
                            <label class="form-label" for="email">Correo electrónico</label>
                            <input class="form-control @error('email') is-invalid @enderror" id="email" name="email" type="email" value="{{ old('email') }}" autocomplete="email" required autofocus @error('email') aria-invalid="true" aria-describedby="email-error" @enderror>
                            @error('email')
                                <div class="invalid-feedback" id="email-error">{{ $message }}</div>
                            @enderror
                        </div>

                        <button class="btn btn-brand w-100" type="submit">Enviar enlace</button>
                    </form>

                    <a class="d-inline-block mt-4" href="{{ route('login') }}">Volver al inicio de sesión</a>
                </div>
            </div>
        </div>
    </section>
@endsection
