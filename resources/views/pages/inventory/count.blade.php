<x-layouts.app title="Stock count">
    <x-page-header title="Stock count" :subtitle="$location">
        <x-slot:actions><x-btn variant="secondary" :href="route('inventory.index')">Balances</x-btn></x-slot:actions>
    </x-page-header>
    @if ($flash)<div class="mb-4 rounded-lg border border-emerald-300 bg-emerald-50 px-4 py-3 text-sm text-emerald-900">{{ $flash }}</div>@endif
    <x-card title="Variance" flush>
        <div class="overflow-x-auto"><table class="data-table" data-testid="variance"><thead><tr><th>Item</th><th class="text-right">Expected</th><th class="text-right">Counted</th><th class="text-right">Variance</th></tr></thead><tbody>
        @foreach ($count['lines'] as $l)
            @php $zero = \App\Support\Money::cmp($l['variance'], '0') === 0; @endphp
            <tr class="{{ $zero ? '' : 'bg-amber-50' }}"><td>{{ $names[$l['itemId']] ?? $l['itemId'] }}</td><td class="text-right tabular-nums">{{ $l['expectedQuantity'] }}</td><td class="text-right tabular-nums">{{ $l['countedQuantity'] }}</td><td class="text-right tabular-nums {{ $zero ? '' : 'font-semibold text-red-800' }}">{{ $l['variance'] }}</td></tr>
        @endforeach
        </tbody></table></div>
    </x-card>
    <div class="flex items-center gap-3">
        <x-badge :status="$count['status']" />
        @if (($count['status'] ?? '') === 'DRAFT' && auth_staff()->can('inventory.count.post'))
            <form method="POST" action="{{ route('inventory.count.post', $count['id']) }}">@csrf<x-btn onclick="return confirm('Post this count? Variance movements will be written.')">Post count</x-btn></form>
        @elseif (($count['status'] ?? '') === 'DRAFT')<span class="text-sm text-stone-600">You do not have permission to post counts.</span>@endif
    </div>
</x-layouts.app>
