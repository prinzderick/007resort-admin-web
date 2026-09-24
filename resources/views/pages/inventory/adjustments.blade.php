<x-layouts.app title="Stock adjustments">
    <x-page-header title="Stock adjustments" subtitle="Manual adjustments and count variances. Ones over the threshold wait in the approvals queue; the ledger only changes once approved.">
        <x-slot:actions>@if (auth_staff()->can('inventory.adjustment.request'))<x-btn variant="secondary" :href="route('inventory.form', 'adjust')">Adjust stock</x-btn>@endif</x-slot:actions>
    </x-page-header>
    <x-inventory-nav />
    <form method="GET" class="mb-5 flex items-end gap-3"><div><label class="mb-1 block text-sm font-medium">Status</label>
        <select name="status" class="min-h-11 rounded-lg border border-stone-300 bg-white px-3 text-sm" onchange="this.form.submit()"><option value="">Any</option>@foreach (['PENDING_APPROVAL', 'POSTED', 'REJECTED', 'CANCELLED'] as $s)<option @selected($status === $s)>{{ $s }}</option>@endforeach</select></div></form>
    <x-card flush>
        <x-fetch :of="$adjustments" what="Adjustments" />
        @if ($adjustments->ok())
            <div class="table-scroll"><table class="data-table" data-testid="adjustments-table"><thead><tr><th>Requested</th><th>Location</th><th>Kind</th><th>Lines</th><th>Reason</th><th>Status</th></tr></thead><tbody>
            @forelse ($adjustments->items() as $a)
                <tr><td><x-time :at="$a['createdAt'] ?? null" /></td><td>{{ $locNames[$a['locationId'] ?? ''] ?? '' }}</td><td>{{ str_replace('_', ' ', $a['kind'] ?? '') }}</td>
                    <td class="text-xs">@foreach ($a['lines'] ?? [] as $l){{ $itemNames[$l['itemId'] ?? ''] ?? '' }}: <b>{{ $l['quantityDelta'] ?? '' }}</b><br>@endforeach</td>
                    <td class="text-xs">{{ $a['reason'] ?? '' }}@if (! empty($a['note']))<div class="text-stone-500">{{ $a['note'] }}</div>@endif</td>
                    <td><x-badge :status="$a['status'] ?? 'UNKNOWN'" /></td></tr>
            @empty<tr><td colspan="6" class="text-center text-stone-500">No adjustments.</td></tr>@endforelse
            </tbody></table></div>
            <x-pagination :count="count($adjustments->items())" :next="$adjustments->next()" />
        @endif
    </x-card>
</x-layouts.app>
