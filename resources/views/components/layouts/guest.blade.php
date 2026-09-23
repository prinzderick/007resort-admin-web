@props(['title' => 'Sign in'])
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="robots" content="noindex,nofollow">
        <title>{{ $title }} - {{ config('app.name') }}</title>
        @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
            @vite(['resources/css/app.css', 'resources/js/app.js'])
        @endif
    </head>
    <body class="flex min-h-screen items-center justify-center bg-stone-100 px-4 text-stone-900 antialiased">
        <div class="w-full max-w-sm">
            <div class="mb-6 text-center">
                <div class="text-xl font-semibold tracking-tight">007 Resort &amp; Spa</div>
                <div class="text-sm text-stone-500">Management portal &middot; {{ config('r007.instance') === 'cloud' ? 'Remote' : 'On-site' }}</div>
                @if (config('r007.mock'))
                    <div class="mt-2 inline-block rounded-full bg-fuchsia-100 px-2.5 py-1 text-xs font-semibold text-fuchsia-900">MOCK DATA</div>
                @endif
            </div>
            <div class="rounded-2xl border border-stone-200 bg-white p-6 shadow-sm">
                <x-alerts />
                {{ $slot }}
            </div>
        </div>
    </body>
</html>
