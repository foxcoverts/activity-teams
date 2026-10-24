<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>{{ __('Privacy notice') }}</title>
        <script src="https://cdn.tailwindcss.com?plugins=typography"></script>
    </head>
    <body class="min-h-screen bg-slate-50 text-slate-900 antialiased">
        <main class="mx-auto max-w-3xl px-4 py-16">
            <article class="prose prose-slate max-w-none rounded-xl bg-white p-6 shadow sm:p-8">
                {!! $content !!}
            </article>
        </main>
    </body>
</html>
