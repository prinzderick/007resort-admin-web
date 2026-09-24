@php $availability = []; foreach ($avail->items() as $a) { $availability[$a['productId'] ?? ''] = (bool) ($a['available'] ?? true); } @endphp
<x-card title="Products sold here" subtitle="Price shown is what applies at this facility" flush>
    <x-fetch :of="$products" what="Products" />
    @if ($products->ok())
        <div class="overflow-x-auto"><table class="data-table" data-testid="fac-products"><thead><tr><th>Product</th><th>Kind</th><th class="text-right">Price</th><th>Available</th></tr></thead><tbody>
        @forelse ($products->items() as $p)
            @php $on = $availability[$p['id'] ?? ''] ?? true; @endphp
            <tr><td class="font-medium">{{ $p['name'] ?? '' }}<div class="text-xs font-normal text-stone-500">{{ $p['sku'] ?? '' }}</div></td><td>{{ $p['kind'] ?? '' }}</td><td class="text-right"><x-money :value="$p['price'] ?? '0'" /></td><td><x-badge :tone="$on ? 'good' : 'bad'">{{ $on ? 'Yes' : 'No (86)' }}</x-badge></td></tr>
        @empty<tr><td colspan="4"><x-empty title="No products at this facility" text="Add products and choose this facility on the Catalog page." icon="tag" /></td></tr>@endforelse
        </tbody></table></div>
    @endif
    <div class="border-t border-stone-100 p-4"><x-btn variant="secondary" :href="route('setup.catalog', ['facility' => $id])">Edit products, prices and availability here</x-btn></div>
</x-card>
