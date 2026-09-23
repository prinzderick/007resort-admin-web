<x-layouts.app title="Catalog">
    <x-page-header title="Products, prices & categories" subtitle="Prices and tax are resolved by the API. You can mark an item unavailable per facility." />
    <x-config-nav />
    <form method="GET" class="mb-5 flex items-end gap-3"><div><label class="mb-1 block text-sm font-medium">Facility (for availability)</label>
        <select name="facility" class="min-h-11 rounded-lg border border-stone-300 bg-white px-3 text-sm" onchange="this.form.submit()">@foreach ($facilities as $f)<option value="{{ $f['id'] }}" @selected($facilityId === $f['id'])>{{ $f['name'] }}</option>@endforeach</select></div></form>
    <x-card title="Products" flush>
        <x-fetch :of="$products" what="Products" />
        @if ($products->ok())
            <div class="overflow-x-auto"><table class="data-table" data-testid="products"><thead><tr><th>Product</th><th>Category</th><th>Kind</th><th class="text-right">Price</th><th>Tax</th><th>Prep route</th><th>Available here</th></tr></thead><tbody>
            @foreach ($products->items() as $p)
                @php $on = $availability[$p['id']] ?? true; @endphp
                <tr><td class="font-medium">{{ $p['name'] }}<div class="text-xs font-normal text-stone-500">{{ $p['sku'] ?? '' }}</div></td><td>{{ $catNames[$p['categoryId']] ?? '' }}</td><td>{{ $p['kind'] }}</td><td class="text-right"><x-money :value="$p['price']" /></td>
                    <td class="text-xs">{{ ($p['taxInclusive'] ?? false) ? 'incl. ' : 'excl. ' }}{{ $p['taxRatePercent'] ?? '0' }}%</td><td>{{ $p['prepRoute']['stationName'] ?? $p['prepRoute']['kind'] ?? '-' }}</td>
                    <td>@if (auth_staff()->can('catalog.availability.manage') && $facilityId)
                        <form method="POST" action="{{ route('config.availability', $p['id']) }}" class="flex items-center gap-2">@csrf @method('PUT')<input type="hidden" name="facilityId" value="{{ $facilityId }}"><input type="hidden" name="available" value="{{ $on ? 0 : 1 }}">
                            <x-badge :tone="$on ? 'good' : 'bad'">{{ $on ? 'Yes' : 'No (86)' }}</x-badge><button class="min-h-10 rounded-lg border border-stone-300 px-3 text-xs hover:bg-stone-50">{{ $on ? 'Mark unavailable' : 'Make available' }}</button></form>
                        @else<x-badge :tone="$on ? 'good' : 'bad'">{{ $on ? 'Yes' : 'No' }}</x-badge>@endif</td></tr>
            @endforeach
            </tbody></table></div>
        @endif
    </x-card>
    <x-card title="Categories" flush>
        <x-fetch :of="$categories" what="Categories" />
        @if ($categories->ok())<ul class="flex flex-wrap gap-2 p-4 text-sm">@foreach ($categories->items() as $c)<li class="rounded-lg border border-stone-200 px-3 py-1.5">{{ $c['name'] }}</li>@endforeach</ul>@endif
    </x-card>
    <x-pending-api :items="['Create / edit products and change prices (no product write endpoints in the contract yet)', 'Create / edit categories', 'Per-product or per-category tax overrides']" />
</x-layouts.app>
