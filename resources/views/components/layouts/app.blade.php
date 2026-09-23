@props(['title' => null])
@php
    $staff = app(\App\Auth\StaffSession::class);
    $nav = \App\Support\Navigation::for($staff);
    $instance = config('r007.instance');
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="robots" content="noindex,nofollow">
        <title>{{ $title ? $title.' - ' : '' }}{{ config('app.name') }}</title>
        @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
            @vite(['resources/css/app.css', 'resources/js/app.js'])
        @endif
        @livewireStyles
    </head>
    <body class="min-h-screen bg-stone-100 text-stone-900 antialiased" x-data="{ menu: false }">
        <div class="flex min-h-screen">
            {{-- Sidebar (drawer below lg: tablets in portrait) --}}
            <div x-cloak x-show="menu" class="fixed inset-0 z-30 bg-stone-900/40 lg:hidden" @click="menu = false"></div>
            <aside class="fixed inset-y-0 left-0 z-40 w-64 -translate-x-full overflow-y-auto border-r border-stone-200 bg-white transition-transform lg:static lg:translate-x-0"
                   :class="{ 'translate-x-0': menu }">
                <div class="px-5 py-5">
                    <div class="text-base font-semibold tracking-tight">007 Resort &amp; Spa</div>
                    <div class="text-xs text-stone-500">Management portal</div>
                </div>
                <nav class="space-y-1 px-3 pb-6" aria-label="Main">
                    @foreach ($nav as $item)
                        <a href="{{ route($item['route']) }}"
                           class="flex min-h-11 items-center rounded-lg px-3 text-sm font-medium {{ request()->routeIs($item['match']) ? 'bg-stone-900 text-white' : 'text-stone-700 hover:bg-stone-100' }}">
                            {{ $item['label'] }}
                        </a>
                    @endforeach
                </nav>
            </aside>

            <div class="flex min-w-0 flex-1 flex-col">
                <header class="sticky top-0 z-20 flex items-center gap-3 border-b border-stone-200 bg-white px-4 py-2">
                    <button type="button" class="min-h-11 min-w-11 rounded-lg border border-stone-300 px-3 text-sm lg:hidden" @click="menu = true" aria-label="Open menu">Menu</button>
                    <div class="flex flex-1 flex-wrap items-center gap-2">
                        <span class="rounded-full px-2.5 py-1 text-xs font-semibold {{ $instance === 'cloud' ? 'bg-sky-100 text-sky-900' : 'bg-emerald-100 text-emerald-900' }}"
                              title="{{ $instance === 'cloud' ? 'Remote (cloud) instance: figures are mirrored from the site' : 'On-site (local) instance' }}">
                            {{ $instance === 'cloud' ? 'CLOUD' : 'LOCAL' }}
                        </span>
                        @if (config('r007.mock'))
                            <span class="rounded-full bg-fuchsia-100 px-2.5 py-1 text-xs font-semibold text-fuchsia-900" title="Serving fixture data, not the real API">MOCK DATA</span>
                        @endif
                    </div>
                    @if ($staff->check())
                        <div class="text-right text-sm leading-tight">
                            <div class="font-medium">{{ $staff->name() }}</div>
                            <div class="text-xs text-stone-500">{{ implode(', ', $staff->roles()) }}</div>
                        </div>
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button class="min-h-11 rounded-lg border border-stone-300 px-3 text-sm hover:bg-stone-50">Sign out</button>
                        </form>
                    @endif
                </header>

                <main class="mx-auto w-full max-w-7xl flex-1 px-4 py-6">
                    <x-alerts />
                    {{ $slot }}
                </main>
                <footer class="px-4 pb-4 text-center text-xs text-stone-400">
                    Times shown in {{ config('r007.display_timezone') }} &middot; API contract {{ \App\Support\Contract::version() ?? '?' }}
                </footer>
            </div>
        </div>
        @livewireScripts
    </body>
</html>
