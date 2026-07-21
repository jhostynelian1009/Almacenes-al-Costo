<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="description" content="@yield('description', 'Almacenes al Costo, una experiencia comercial clara y cercana.')">
    <title>@yield('title', 'Almacenes al Costo')</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700;800&amp;display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @stack('styles')
</head>
<body class="@yield('body-class')">
    <a class="skip-link btn btn-light" href="#main-content">Saltar al contenido principal</a>
    @yield('body')
    @stack('scripts')
</body>
</html>
