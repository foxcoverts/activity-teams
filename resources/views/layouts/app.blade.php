<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ?? config('app.name') }}</title>
    @if (filled($description ?? null))
        <meta name="description" content="{{ $description }}">
        <meta property="og:title" content="{{ $title }}">
        <meta property="og:description" content="{{ $description }}">
        <meta property="og:url" content="{{ $url }}">
        <meta property="og:type" content="website">
        <meta property="og:image" content="{{ $image }}">
        <meta property="og:image:width" content="700">
        <meta property="og:image:height" content="700">
    @endif
    <script src="https://cdn.tailwindcss.com"></script>
    @livewireStyles
</head>

<body class="min-h-screen bg-slate-50 text-slate-900 antialiased">
    <main class="mx-auto max-w-lg px-4 py-8">
        {{ $slot }}
    </main>
    @livewireScripts
</body>

</html>
