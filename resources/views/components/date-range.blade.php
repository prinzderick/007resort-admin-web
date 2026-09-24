@php
    $range = \App\Support\DateRange::fromRequest();
    $keep = request()->except(['from', 'to', 'range', 'cursor', 'page']);
    $url = fn (array $q) => request()->url().'?'.http_build_query($keep + $q);
@endphp
<div class="relative" x-data="{ open: false }" data-testid="date-range">
    <button type="button" @click="open = !open" @keydown.escape.window="open = false" class="flex min-h-10 items-center gap-2 rounded-lg border border-stone-300 bg-white px-3 text-sm font-medium hover:bg-stone-50" :aria-expanded="open">
        <x-icon name="calendar" class="size-4 text-stone-500" /><span>{{ $range->label() }}</span><x-icon name="chevron" class="size-4 text-stone-400" />
    </button>
    <div x-cloak x-show="open" @click.outside="open = false" class="absolute right-0 z-40 mt-2 w-72 rounded-xl border border-stone-200 bg-white p-2 shadow-lg">
        @foreach (\App\Support\DateRange::PRESETS as $k => $label)
            @continue($k === 'custom')
            <a href="{{ $url(['range' => $k]) }}" class="block rounded-lg px-3 py-2 text-sm hover:bg-stone-100 {{ $range->preset === $k ? 'bg-brand-50 font-semibold text-brand-700' : '' }}">{{ $label }}</a>
        @endforeach
        <form method="GET" action="{{ request()->url() }}" class="mt-2 border-t border-stone-100 p-2">
            @foreach ($keep as $k => $v)@if (is_scalar($v))<input type="hidden" name="{{ $k }}" value="{{ $v }}">@endif @endforeach
            <div class="mb-2 text-xs font-semibold uppercase tracking-wide text-stone-500">Custom range</div>
            <div class="flex items-center gap-2"><input type="date" name="from" value="{{ $range->from }}" max="{{ \App\Support\Time::today() }}" class="min-h-10 w-full rounded-lg border border-stone-300 px-2 text-sm"><span class="text-stone-400">&ndash;</span><input type="date" name="to" value="{{ $range->to }}" max="{{ \App\Support\Time::today() }}" class="min-h-10 w-full rounded-lg border border-stone-300 px-2 text-sm"></div>
            <button class="mt-2 min-h-10 w-full rounded-lg bg-brand-600 text-sm font-medium text-white hover:bg-brand-700">Apply</button>
        </form>
    </div>
</div>
