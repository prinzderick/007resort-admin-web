<x-layouts.app title="Search">
    <x-page-header title="Search" :subtitle="$q === '' ? 'Type in the box above to find a page, facility, staff member, product, order or customer.' : 'Results for &ldquo;'.e($q).'&rdquo;'" />
    @if ($q !== '' && $groups === [])
        <x-card><x-empty title="Nothing found" text="Check the spelling, or try a shorter word. Search covers pages, facilities, staff, products, orders, receipts and customers you have access to. Type at least 2 letters." icon="search" /></x-card>
    @endif
    @foreach ($groups as $title => $hits)
        <x-card :title="$title" flush data-testid="search-group">
            <ul class="divide-y divide-stone-100">
                @foreach ($hits as $h)
                    <li><a href="{{ $h['url'] }}" class="flex items-center justify-between gap-3 px-5 py-3 text-sm hover:bg-stone-50"><span class="font-medium">{{ $h['title'] }}</span><span class="text-xs text-stone-500">{{ $h['sub'] }}</span></a></li>
                @endforeach
            </ul>
        </x-card>
    @endforeach
</x-layouts.app>
