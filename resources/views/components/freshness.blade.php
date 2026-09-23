@props(['freshness'])
@php
    /** @var \App\Support\DataFreshness $freshness */
    $styles = [
        'live' => 'border-emerald-300 bg-emerald-50 text-emerald-900',
        'unknown' => 'border-stone-300 bg-stone-100 text-stone-800',
        'stale' => 'border-amber-400 bg-amber-50 text-amber-950',
        'offline' => 'border-red-400 bg-red-50 text-red-950',
    ];
    $label = ['live' => 'LIVE', 'unknown' => 'UNVERIFIED', 'stale' => 'STALE DATA', 'offline' => 'SITE OFFLINE'][$freshness->level];
@endphp
<div class="mb-5 flex flex-wrap items-center gap-3 rounded-xl border-2 px-4 py-3 text-sm {{ $styles[$freshness->level] }}"
     role="{{ $freshness->isLive() ? 'status' : 'alert' }}" data-testid="freshness-banner" data-level="{{ $freshness->level }}">
    <span class="rounded-md bg-white/70 px-2 py-0.5 text-xs font-bold tracking-wide">{{ $label }}</span>
    <span>{{ $freshness->message }}</span>
    @if ($freshness->lastSyncAt && ! $freshness->isLive())
        <span class="text-xs opacity-80">Last sync {{ \App\Support\Time::format($freshness->lastSyncAt) }}</span>
    @endif
</div>
