<x-layouts.app title="Stock count">
    <x-page-header title="Stock count" :subtitle="$doc->ok() ? ($locNames[$doc->data['locationId'] ?? ''] ?? '') : null">
        <x-slot:actions><x-btn variant="secondary" :href="route('inventory.counts')">All counts</x-btn></x-slot:actions>
    </x-page-header>
    @if ($flash)<div class="mb-4 rounded-lg border border-emerald-300 bg-emerald-50 px-4 py-3 text-sm text-emerald-900">{{ $flash }}</div>@endif
    <x-fetch :of="$doc" what="Count sheet" />
    @if ($doc->ok())
        @php $count = (array) $doc->data; $status = $count['status'] ?? 'UNKNOWN'; @endphp
        <x-card title="Variance" flush>
            <div class="table-scroll"><table class="data-table" data-testid="variance"><thead><tr><th>Item</th><th class="text-right">Expected</th><th class="text-right">Counted</th><th class="text-right">Variance</th><th>Line status</th></tr></thead><tbody>
            @foreach ($count['lines'] ?? [] as $l)
                @php $v = $l['variance'] ?? null; $zero = $v === null || \App\Support\Money::cmp($v, '0') === 0; @endphp
                <tr class="{{ $zero ? '' : 'bg-amber-50' }}"><td>{{ $names[$l['itemId'] ?? ''] ?? ($l['itemId'] ?? '') }}</td><td class="text-right tabular-nums">{{ $l['expectedQuantity'] ?? '' }}</td><td class="text-right tabular-nums">{{ $l['countedQuantity'] ?? '' }}</td><td class="text-right tabular-nums {{ $zero ? '' : 'font-semibold text-red-800' }}">{{ $v ?? 'after posting' }}</td><td class="text-xs">{{ $l['varianceStatus'] ?? '' }}</td></tr>
            @endforeach
            </tbody></table></div>
            @if (! empty($count['note']))<p class="border-t border-stone-100 p-3 text-xs text-stone-600">Note: {{ $count['note'] }}</p>@endif
        </x-card>
        <div class="flex items-center gap-3">
            <x-badge :status="$status" />
            @if ($status === 'DRAFT' && auth_staff()->can('inventory.count.post'))
                <form method="POST" action="{{ route('inventory.count.post', $id) }}">@csrf<x-btn onclick="return confirm('Post this count? Variance movements will be written against the live balances.')">Post count</x-btn></form>
            @elseif ($status === 'DRAFT')<span class="text-sm text-stone-600">You do not have permission to post counts.</span>@endif
            @if (! empty($count['approvalId']))<span class="text-sm text-stone-600">Waiting for approval ({{ \App\Services\Portal\Directory::short($count['approvalId']) }}).</span>@endif
        </div>
    @endif
</x-layouts.app>
