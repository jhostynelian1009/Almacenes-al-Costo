@extends('layouts.guest')

@section('title', 'Iniciar sesión | Almacenes al Costo')
@section('description', 'Acceso seguro para usuarios internos de Almacenes al Costo.')

@section('content')
    <section class="auth-section" aria-labelledby="login-title">
        <div class="container px-3">
            <div class="card auth-card mx-auto">
                <div class="card-body p-4 p-md-5">
                    <p class="fw-bold mb-2">Almacenes al Costo</p>
                    <h1 class="h2" id="login-title">Iniciar sesión</h1>
                    <p class="text-body-secondary">Acceso exclusivo para Administradores y Empleados.</p>

                    <form method="POST" action="{{ route('login.store') }}" novalidate>
                        @csrf

                        <div class="mb-3">
                            <label class="form-label" for="email">Correo electrónico</label>
                            <input class="form-control @error('email') is-invalid @enderror" id="email" name="email" type="email" value="{{ old('email') }}" autocomplete="username" required autofocus @error('email') aria-invalid="true" aria-describedby="email-error" @enderror>
                            @error('email')
                                <div class="invalid-feedback" id="email-error">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="mb-3">
                            <label class="form-label" for="password">Contraseña</label>
                            <input class="form-control @error('password') is-invalid @enderror" id="password" name="password" type="password" autocomplete="current-password" required @error('password') aria-invalid="true" aria-describedby="password-error" @enderror>
                            @error('password')
                                <div class="invalid-feedback" id="password-error">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="form-check mb-4">
                            <input class="form-check-input" id="remember" name="remember" type="checkbox" value="1" @checked(old('remember'))>
                            <label class="form-check-label" for="remember">Recordarme</label>
                        </div>

                        <button class="btn btn-brand w-100" type="submit">Iniciar sesión</button>
                    </form>

                    <a class="d-inline-block mt-4" href="{{ route('home') }}">Volver al sitio público</a>
                </div>
            </div>
        </div>
    </section>
@endsection
