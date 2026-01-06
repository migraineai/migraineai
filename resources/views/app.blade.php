<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="min-h-full">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title inertia>{{ config('app.name', 'Migraine Tracker') }}</title>
        <style>[v-cloak]{display:none}</style>

        <link rel="icon" href="{{ asset('favicon.ico') }}">
        <link rel="shortcut icon" href="{{ asset('favicon.ico') }}">
        <link rel="apple-touch-icon" sizes="180x180" href="{{ asset('logo.png') }}">
        <link rel="mask-icon" href="{{ asset('logo.png') }}" color="#5f9e86">

        @if(config('services.google_analytics.measurement_id'))
            <!-- Google Analytics -->
            <script async src="https://www.googletagmanager.com/gtag/js?id={{ config('services.google_analytics.measurement_id') }}"></script>
            <script>
                window.dataLayer = window.dataLayer || [];
                function gtag(){dataLayer.push(arguments);}
                gtag('js', new Date());
                gtag('config', '{{ config('services.google_analytics.measurement_id') }}');
            </script>
        @endif

        @vite(['resources/js/app.ts'])
        @inertiaHead
    </head>
    <body class="min-h-screen bg-[--color-bg] text-[--color-text-primary] antialiased">
        @inertia
    </body>
</html>
