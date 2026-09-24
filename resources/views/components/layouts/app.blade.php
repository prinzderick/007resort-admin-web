@props(['title' => null, 'range' => false])
@php
    $staff = app(\App\Auth\StaffSession::class);
    $groups = \App\Support\Navigation::for($staff);
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
            <div x-cloak x-show="menu" class="fixed inset-0 z-30 bg-stone-900/40 lg:hidden" @click="menu = false"></div>
            {{-- Sidebar: always visible from lg up, a drawer below (tablets in portrait) --}}
            <aside class="fixed inset-y-0 left-0 z-40 flex w-64 -translate-x-full flex-col overflow-y-auto border-r border-stone-200 bg-white transition-transform lg:sticky lg:top-0 lg:h-screen lg:translate-x-0"
                   :class="{ 'translate-x-0': menu }" aria-label="Main navigation">
                <a href="{{ route('dashboard') }}" class="flex items-center gap-3 px-5 py-5">
                    <span class="flex size-9 items-center justify-center rounded-lg bg-brand-600 text-sm font-bold text-white">007</span>
                    <span class="leading-tight"><span class="block text-sm font-semibold tracking-tight">007 Resort &amp; Spa</span><span class="block text-xs text-stone-500">Management portal</span></span>
                </a>
                <nav class="flex-1 px-3 pb-6" data-testid="sidebar">
                    @foreach ($groups as $group)
                        <div class="nav-group-title">{{ $group['title'] }}</div>
                        <div class="space-y-0.5">
                            @foreach ($group['items'] as $item)
                                @if ($item['state'] === 'enabled')
                                    <a href="{{ route($item['route']) }}" class="nav-item" @if (\App\Support\Navigation::isActive($item['match'])) aria-current="page" @endif data-nav="{{ $item['route'] }}">
                                        <x-icon :name="$item['icon']" class="size-[18px] shrink-0" /><span class="truncate">{{ $item['label'] }}</span>
                                        @if (($item['badge'] ?? null) === 'approvals')<livewire:approvals-badge />@endif
                                    </a>
                                @else
                                    <span class="nav-item" aria-disabled="true" title="{{ $item['missing'] }}" data-nav-disabled="{{ $item['route'] }}">
                                        <x-icon :name="$item['icon']" class="size-[18px] shrink-0" /><span class="truncate">{{ $item['label'] }}</span><x-icon name="lock" class="ml-auto size-3.5" />
                                    </span>
                                @endif
                            @endforeach
                        </div>
                    @endforeach
                </nav>
            </aside>

            <div class="flex min-w-0 flex-1 flex-col">
                <header class="sticky top-0 z-20 flex flex-wrap items-center gap-x-3 gap-y-2 border-b border-stone-200 bg-white px-4 py-2.5">
                    <button type="button" class="flex size-10 items-center justify-center rounded-lg border border-stone-300 lg:hidden" @click="menu = true" aria-label="Open menu"><x-icon name="menu" /></button>
                    <form method="GET" action="{{ route('search') }}" class="relative min-w-40 flex-1 md:max-w-md" role="search">
                        <x-icon name="search" class="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-stone-400" />
                        <input type="search" name="q" value="{{ request()->routeIs('search') ? request('q') : '' }}" placeholder="Search staff, facilities, products, pages..." aria-label="Search" class="min-h-10 w-full rounded-lg border border-stone-200 bg-stone-50 pl-9 pr-3 text-sm focus:border-brand-500 focus:bg-white focus:outline-none">
                    </form>
                    <div class="ml-auto flex flex-wrap items-center gap-2">
                        <span class="rounded-full px-2.5 py-1 text-xs font-semibold {{ $instance === 'cloud' ? 'bg-sky-100 text-sky-900' : 'bg-stone-100 text-stone-700' }}" title="{{ $instance === 'cloud' ? 'Remote (cloud) instance: figures are mirrored from the site' : 'On-site (local) instance' }}">{{ $instance === 'cloud' ? 'CLOUD' : 'LOCAL' }}</span>
                        @if (config('r007.mock'))<span class="rounded-full bg-fuchsia-100 px-2.5 py-1 text-xs font-semibold text-fuchsia-900" title="Serving fixture data, not the real API">MOCK DATA</span>@endif
                        @if ($range)<x-date-range />@endif
                        @if ($staff->check())
                            <livewire:site-status />
                            <livewire:notifications />
                            <div class="relative" x-data="{ open: false }">
                                <button type="button" @click="open = !open" @keydown.escape.window="open = false" class="flex min-h-10 items-center gap-2 rounded-full py-1 pl-1 pr-2 hover:bg-stone-100" :aria-expanded="open" data-testid="user-menu">
                                    <span class="flex size-8 items-center justify-center rounded-full bg-brand-100 text-sm font-semibold text-brand-800">{{ mb_strtoupper(mb_substr($staff->name(), 0, 1)) }}</span>
                                    <span class="hidden text-left text-sm leading-tight md:block"><span class="block font-medium">{{ $staff->name() }}</span><span class="block text-xs text-stone-500">{{ implode(', ', $staff->roles()) }}</span></span>
                                    <x-icon name="chevron" class="size-4 text-stone-400" />
                                </button>
                                <div x-cloak x-show="open" @click.outside="open = false" class="absolute right-0 z-40 mt-2 w-56 rounded-xl border border-stone-200 bg-white p-2 shadow-lg">
                                    <div class="px-3 py-2 text-sm"><div class="font-medium">{{ $staff->name() }}</div><div class="text-xs text-stone-500">{{ implode(', ', $staff->roles()) }}</div></div>
                                    <form method="POST" action="{{ route('logout') }}" class="border-t border-stone-100 pt-1">@csrf<button class="w-full rounded-lg px-3 py-2 text-left text-sm hover:bg-stone-100">Sign out</button></form>
                                </div>
                            </div>
                        @endif
                    </div>
                </header>

                <main class="mx-auto w-full max-w-[90rem] flex-1 px-4 py-6 lg:px-8">
                    <x-alerts />
                    {{ $slot }}
                </main>
                <footer class="px-4 pb-4 text-center text-xs text-stone-400">
                    Times shown in {{ config('r007.display_timezone') }} &middot; API contract {{ \App\Support\Contract::version() ?? '?' }}
                </footer>
            </div>
        </div>
        <x-drawer />
        <x-toasts />
        @livewireScripts
    </body>
</html>
