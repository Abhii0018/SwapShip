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
<div class="nv-noise" aria-hidden="true"></div>
@include('layouts.navigation')
<main class="{{ request()->routeIs('home') ? '' : 'container section' }}">
    @if (session('success'))
        <div class="nv-alert nv-alert-success">{{ session('success') }}</div>
    @endif
    @if (session('error'))
        <div class="nv-alert nv-alert-error">{{ session('error') }}</div>
    @endif
    {{ $slot }}
</main>
</body>
</html>
