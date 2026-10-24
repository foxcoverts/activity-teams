<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>{{ $title ?? config('app.name') }}</title>
        @filamentStyles
        @livewireStyles
        <script src="https://cdn.tailwindcss.com"></script>
    </head>
    <body class="min-h-screen bg-slate-50 text-slate-900 antialiased">
        <main class="mx-auto {{ $maxWidth ?? 'max-w-lg' }} px-4 py-8 sm:py-12">
            {{ $slot }}
        </main>
        @filamentScripts
        @livewireScripts
    </body>
</html>
