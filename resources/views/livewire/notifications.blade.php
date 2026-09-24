<div wire:poll.60s x-data="{ open: false }" class="relative" data-testid="notifications">
    <button type="button" @click="open = !open" @keydown.escape.window="open = false" class="relative flex size-10 items-center justify-center rounded-full text-stone-600 hover:bg-stone-100" aria-label="Notifications" :aria-expanded="open">
        <x-icon name="bell" />
        @if ($total > 0)<span class="absolute right-1 top-1 flex min-w-4 items-center justify-center rounded-full bg-red-600 px-1 text-[10px] font-bold text-white" data-testid="bell-count">{{ $total > 99 ? '99+' : $total }}</span>@endif
    </button>
    <div x-cloak x-show="open" @click.outside="open = false" class="absolute right-0 z-40 mt-2 w-80 rounded-xl border border-stone-200 bg-white shadow-lg">
        <div class="border-b border-stone-100 px-4 py-3 text-sm font-semibold">Needs attention</div>
        @forelse ($items as $i)
            <a href="{{ route($i['route']) }}" class="flex items-start gap-3 border-b border-stone-100 px-4 py-3 text-sm last:border-0 hover:bg-stone-50">
                <span class="mt-1.5 size-2 shrink-0 rounded-full {{ $i['tone'] === 'bad' ? 'bg-red-600' : 'bg-amber-500' }}"></span><span>{{ $i['text'] }}</span>
            </a>
        @empty
            <p class="px-4 py-6 text-center text-sm text-stone-500">Nothing needs your attention.</p>
        @endforelse
    </div>
</div>
