<x-layouts.app title="Stock movements">
    <x-page-header title="Stock movements" subtitle="The append-only stock ledger, newest first. Receipts, transfers, sales consumption, wastage and adjustments." />
    <x-inventory-nav />
    <form method="GET" class="mb-5 flex flex-wrap items-end gap-3">
        <div><label class="mb-1 block text-sm font-medium">Item</label><select name="item" class="min-h-11 rounded-lg border border-stone-300 bg-white px-3 text-sm"><option value="">Any</option>@foreach ($items->items() as $i)<option value="{{ $i['id'] ?? '' }}" @selected(($q['item'] ?? '') === ($i['id'] ?? ''))>{{ $i['name'] ?? '' }}</option>@endforeach</select></div>
        <div><label class="mb-1 block text-sm font-medium">Location</label><select name="location" class="min-h-11 rounded-lg border border-stone-300 bg-white px-3 text-sm"><option value="">Any</option>@foreach ($locations->items() as $l)<option value="{{ $l['id'] ?? '' }}" @selected(($q['location'] ?? '') === ($l['id'] ?? ''))>{{ $l['name'] ?? '' }}</option>@endforeach</select></div>
        <div><label class="mb-1 block text-sm font-medium">Reason</label><input name="reason" value="{{ $q['reason'] ?? '' }}" placeholder="e.g. SALE, WASTAGE" class="min-h-11 rounded-lg border border-stone-300 px-3 text-sm"></div>
        <x-btn variant="secondary">Filter</x-btn>
    </form>
    <x-card flush>
        <x-fetch :of="$moves" what="Stock movements" />
        @if ($moves->ok())
            <div class="table-scroll"><table class="data-table" data-testid="movements-table"><thead><tr><th>When</th><th>Item</th><th>Location</th><th>Kind</th><th class="text-right">Change</th><th class="text-right">Balance</th><th>By</th></tr></thead><tbody>
            @forelse ($moves->items() as $m)
                @php $d = (string) ($m['quantityDelta'] ?? '0'); $neg = str_starts_with($d, '-'); @endphp
                <tr><td><x-time :at="$m['createdAt'] ?? null" /></td><td>{{ $itemNames[$m['itemId'] ?? ''] ?? \App\Services\Portal\Directory::short($m['itemId'] ?? null) }}</td>
                    <td>{{ $locNames[$m['locationId'] ?? ''] ?? \App\Services\Portal\Directory::short($m['locationId'] ?? null) }}@if (! empty($m['counterpartLocationId']))<div class="text-xs text-stone-500">&harr; {{ $locNames[$m['counterpartLocationId']] ?? '' }}</div>@endif</td>
                    <td>{{ str_replace('_', ' ', $m['kind'] ?? '') }}<div class="text-xs text-stone-500">{{ $m['reason'] ?? '' }}@if (! empty($m['note'])) &middot; {{ $m['note'] }}@endif</div></td>
                    <td class="text-right tabular-nums {{ $neg ? 'text-red-800' : 'text-emerald-800' }}">{{ $neg ? '' : '+' }}{{ $d }}</td><td class="text-right tabular-nums">{{ $m['balanceAfter'] ?? '' }}</td>
                    <td class="text-xs">{{ ($m['actorStaffId'] ?? null) ? ($staffNames[$m['actorStaffId']] ?? \App\Services\Portal\Directory::short($m['actorStaffId'])) : 'system' }}</td></tr>
            @empty<tr><td colspan="7" class="text-center text-stone-500">No movements.</td></tr>@endforelse
            </tbody></table></div>
            <x-pagination :count="count($moves->items())" :next="$moves->next()" />
        @endif
    </x-card>
</x-layouts.app>
