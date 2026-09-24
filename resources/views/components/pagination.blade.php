@props(['count' => 0, 'next' => null, 'noun' => 'rows'])
<div class="flex flex-wrap items-center justify-between gap-3 border-t border-stone-100 px-4 py-3 text-sm" data-component="pagination">
    <span class="text-stone-500">Showing <b class="font-semibold text-stone-800">{{ $count }}</b> {{ $noun }}{{ $next ? ' on this page, more available' : '' }}{{ request()->query('cursor') ? '' : '' }}</span>
    <span class="flex items-center gap-2">
        @if (request()->query('cursor'))<x-btn variant="secondary" :href="request()->fullUrlWithoutQuery(['cursor'])">First page</x-btn>@endif
        @if ($next)<x-btn variant="secondary" :href="request()->fullUrlWithQuery(['cursor' => $next])">Next page</x-btn>@endif
    </span>
</div>
