<x-layouts.app title="Inventory">
    <x-page-header title="Inventory" subtitle="Balances by location. Every movement, adjustment and count is written by the API.">
        <x-slot:actions>
            @foreach (\App\Http\Controllers\InventoryController::ACTIONS as $k => [$perm, $label])
                @if (auth_staff()->can($perm))<x-btn variant="secondary" :href="route('inventory.form', $k)">{{ $label }}</x-btn>@endif
            @endforeach
            <x-btn variant="secondary" :href="request()->fullUrlWithQuery(['format' => 'csv'])">Export CSV</x-btn>
        </x-slot:actions>
    </x-page-header>
    <x-inventory-nav />

    <form method="GET" class="mb-5 flex items-end gap-3">
        <div><label class="mb-1 block text-sm font-medium">Location</label>
            <select name="location" class="min-h-11 rounded-lg border border-stone-300 bg-white px-3 text-sm" onchange="this.form.submit()"><option value="">All locations</option>@foreach ($locations->items() as $l)<option value="{{ $l['id'] ?? '' }}" @selected($locationId === ($l['id'] ?? ''))>{{ $l['name'] ?? '' }}</option>@endforeach</select></div>
        @if ($lowCount)<span class="pb-2 text-sm text-red-800" data-testid="low-count">{{ $lowCount }} line(s) at or below reorder level</span>@endif
    </form>

    <x-card title="Balances" flush x-data="tableTools">
        <x-table-tools :csv="true" />
        <x-fetch :of="$balances" what="Balances" />
        @if ($balances->ok())
            <div class="table-scroll"><table class="data-table" data-testid="balances">
                <thead><tr><th>Item</th><th>Location</th><th class="text-right">On hand</th><th class="text-right">Reorder at</th><th>Updated</th></tr></thead>
                <tbody>
                @forelse ($rows as $r)
                    <tr data-row class="{{ $r['low'] ? 'bg-red-50' : '' }}"><td>{{ $r['itemName'] ?? $r['itemId'] ?? '' }}</td><td>{{ $r['location'] }}</td><td class="text-right tabular-nums {{ $r['low'] ? 'font-semibold text-red-800' : '' }}">{{ $r['quantity'] ?? '' }} {{ $r['unit'] ?? '' }}</td><td class="text-right tabular-nums">{{ $r['reorderLevel'] ?? '-' }}</td><td><x-time :at="$r['updatedAt'] ?? null" ago /></td></tr>
                @empty<tr><td colspan="5" class="text-center text-stone-500">No balances.</td></tr>@endforelse
                </tbody></table></div>
            @if ($balances->next())<p class="border-t border-stone-100 p-3 text-xs text-stone-500">Showing the first {{ count($rows) }} lines. Pick a location to narrow the list.</p>@endif
        @endif
    </x-card>
</x-layouts.app>
