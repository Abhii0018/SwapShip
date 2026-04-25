<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ config('app.name', 'SwapShip') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body>
<main class="auth-page" style="min-height:100vh;display:flex;align-items:center;justify-content:center;padding:20px;position:relative;">
    <div class="auth-bg-layer" aria-hidden="true"></div>
    <a href="{{ route('home') }}" class="auth-back-btn">
        <span class="auth-back-icon" aria-hidden="true">&larr;</span>
        <span>Back to Home</span>
    </a>
    <section class="auth-shell" style="width:100%;max-width:560px;margin:0 auto;">
        <a href="{{ route('home') }}" class="auth-logo-link"><x-application-logo /></a>
        {{ $slot }}
    </section>
</main>
</body>
</html>
