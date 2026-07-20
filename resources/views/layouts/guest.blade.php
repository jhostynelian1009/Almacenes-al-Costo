@extends('layouts.app')

@push('styles')
    @vite('resources/css/public.css')
@endpush

@section('body')
    <x-public.navbar />
    <main id="main-content">
        <div class="container pt-3"><x-shared.alerts /></div>
        @yield('content')
    </main>
    <x-public.footer />
@endsection
