<x-layouts.app title="Stock transfers">
    <x-page-header title="Stock transfers" subtitle="Stock moved between stores (Main Store to a facility store, bar or kitchen). Each row is one transfer document.">
        <x-slot:actions>@if (auth_staff()->can('inventory.transfer.create'))<x-btn icon="plus" :href="route('inventory.form', 'transfer')">New transfer</x-btn>@endif</x-slot:actions>
    </x-page-header>
    <x-card flush x-data="tableTools">
        <x-table-tools placeholder="Filter this page (item, store...)" />
        <x-fetch :of="$moves" what="Transfers" />
        @if ($moves->ok())
            <div class="overflow-x-auto"><table class="data-table" data-testid="transfers-table"><thead><tr><th @click="sort(0)" data-sort>When</th><th>From</th><th>To</th><th>Items</th><th>By</th></tr></thead><tbody x-ref="body">
            @forelse ($docs as $d)
                <tr data-row><td data-sort="{{ $d['at'] ?? '' }}"><x-time :at="$d['at'] ?? null" /></td><td>{{ $locNames[$d['from'] ?? ''] ?? '' }}</td><td>{{ $locNames[$d['to'] ?? ''] ?? '' }}</td>
                    <td class="text-xs">@foreach ($d['lines'] ?? [] as $l){{ $itemNames[$l['item'] ?? ''] ?? '' }}: <b>{{ $l['qty'] }}</b><br>@endforeach</td>
                    <td class="text-xs">{{ ($d['actor'] ?? null) ? ($staffNames[$d['actor']] ?? \App\Services\Portal\Directory::short($d['actor'])) : 'system' }}</td></tr>
            @empty<tr><td colspan="5"><x-empty title="No transfers yet" text="Move stock from the Main Store to where it is used." icon="repeat" :action="auth_staff()->can('inventory.transfer.create') ? 'New transfer' : null" :href="route('inventory.form', 'transfer')" /></td></tr>@endforelse
            </tbody></table></div>
        @endif
    </x-card>
</x-layouts.app>
