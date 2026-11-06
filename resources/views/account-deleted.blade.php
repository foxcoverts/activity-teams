<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('Account deleted') }}</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>

<body class="min-h-screen bg-slate-50 text-slate-900 antialiased">
    <main class="mx-auto max-w-lg px-4 py-16">
        <div class="rounded-xl bg-white p-6 shadow">
            <h1 class="text-xl font-semibold">{{ __('Your account has been deleted') }}</h1>
            <p class="mt-3 text-sm">
                {{ __('You can no longer sign in. Your name and operational history are kept. Remaining contact details will be removed after 30 days.') }}
            </p>
        </div>
    </main>
</body>

</html>
