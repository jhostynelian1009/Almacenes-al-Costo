@extends('layouts.app')

@section('body-class', 'public-site d-flex flex-column min-vh-100')

@push('styles')
    @vite('resources/css/public.css')
@endpush

@section('body')
    <header>
        <x-public.navbar />
    </header>

    <main class="public-main flex-grow-1" id="main-content">
        <div class="container pt-3">
            <x-shared.alerts />
        </div>
        @yield('content')
    </main>

    <x-public.footer />
@endsection
