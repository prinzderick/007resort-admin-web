@php
    $tone = ['live' => 'border-brand-200 bg-brand-50 text-brand-800', 'stale' => 'border-amber-300 bg-amber-50 text-amber-900', 'offline' => 'border-red-300 bg-red-50 text-red-900'][$level];
    $dot = ['live' => 'bg-brand-500', 'stale' => 'bg-amber-500', 'offline' => 'bg-red-600'][$level];
@endphp
<div wire:poll.30s x-data="{ open: false }" class="relative" data-testid="site-status" data-level="{{ $level }}">
    <button type="button" @click="open = !open" @keydown.escape.window="open = false" class="flex min-h-10 items-center gap-2 rounded-full border px-3 text-sm font-semibold {{ $tone }}" aria-haspopup="true" :aria-expanded="open">
        <span class="relative flex size-2.5"><span class="absolute inline-flex size-full animate-ping rounded-full opacity-60 {{ $dot }} {{ $level === 'live' ? '' : 'hidden' }}"></span><span class="relative inline-flex size-2.5 rounded-full {{ $dot }}"></span></span>
        {{ $level === 'live' ? 'You are Live' : ($level === 'stale' ? 'Data may be stale' : 'Site offline') }}
        @if ($lastSync)<span class="hidden text-xs font-normal opacity-80 md:inline">&middot; {{ $lastSync }}</span>@endif
    </button>
    <div x-cloak x-show="open" @click.outside="open = false" class="absolute right-0 z-40 mt-2 w-80 rounded-xl border border-stone-200 bg-white p-4 text-sm shadow-lg">
        <div class="font-semibold">{{ $label }}</div>
        <p class="mt-1 text-stone-600">{{ $detail }}</p>
        @if ($lastSync)<p class="mt-2 text-xs text-stone-500">Last sync between the nodes: {{ $lastSync }}</p>@endif
        @if ($checks !== [])
            <div class="mt-3 flex flex-wrap gap-2">@foreach ($checks as $name => $v)<span class="rounded-lg border border-stone-200 px-2 py-1 text-xs">{{ $name }} <x-badge :status="$v" /></span>@endforeach</div>
        @endif
        @if (auth_staff()->can('config.manage'))<a href="{{ route('sync') }}" class="mt-3 inline-block text-xs font-medium text-brand-700 underline">Open Sync &amp; IT</a>@endif
    </div>
</div>
