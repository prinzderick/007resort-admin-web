<x-layouts.app title="Reports">
    <x-page-header title="Reports" subtitle="Start at the property and drill down: facility, terminal, staff, transaction.">
        <x-slot:actions>
            <x-btn variant="secondary" :href="request()->fullUrlWithQuery(['format' => 'csv'])">Export CSV</x-btn>
        </x-slot:actions>
    </x-page-header>
    <x-drill at="Property" />
    <x-freshness :freshness="$freshness" />

    <form method="GET" class="mb-5 flex flex-wrap items-end gap-3">
        <div><label class="mb-1 block text-sm font-medium">Day</label><input type="date" name="date" value="{{ $date }}" class="min-h-11 rounded-lg border border-stone-300 px-3 text-sm"></div>
        <div><label class="mb-1 block text-sm font-medium">Period from</label><input type="date" name="from" value="{{ $from }}" class="min-h-11 rounded-lg border border-stone-300 px-3 text-sm"></div>
        <div><label class="mb-1 block text-sm font-medium">to</label><input type="date" name="to" value="{{ $to }}" class="min-h-11 rounded-lg border border-stone-300 px-3 text-sm"></div>
        <x-btn variant="secondary">Apply</x-btn>
    </form>

    <x-card title="Facilities on {{ $date }}" flush>
        <x-fetch :of="$facilities" what="Facilities" />
        <div class="overflow-x-auto"><table class="data-table" data-testid="property-table">
            <thead><tr><th>Facility</th><th class="text-right">Orders</th><th class="text-right">Gross</th><th class="text-right">Discounts</th><th class="text-right">Net sales</th><th class="text-right">Refunds</th><th class="text-right">Tickets</th></tr></thead>
            <tbody>
            @forelse ($rows as $r)
                <tr><td><a class="font-medium underline" href="{{ route('reports.facility', ['facility' => $r['facility']['id'], 'date' => $date]) }}">{{ $r['facility']['name'] }}</a></td>
                    <td class="text-right tabular-nums">{{ $r['s']['orders'] }}</td><td class="text-right"><x-money :value="$r['s']['grossSales']" /></td><td class="text-right"><x-money :value="$r['s']['discounts'] ?? '0'" /></td>
                    <td class="text-right"><x-money :value="$r['s']['netSales']" /></td><td class="text-right"><x-money :value="$r['s']['refunds'] ?? '0'" /></td><td class="text-right tabular-nums">{{ $r['s']['ticketsRedeemed'] ?? 0 }}</td></tr>
            @empty
                <tr><td colspan="7" class="text-center text-stone-500">No facility figures for this day.</td></tr>
            @endforelse
            </tbody>
            @if ($rows !== [])<tfoot><tr class="font-semibold"><td>Property total</td><td></td><td></td><td></td><td class="text-right"><x-money :value="$total" /></td><td></td><td></td></tr></tfoot>@endif
        </table></div>
    </x-card>

    <x-card title="Period revenue {{ $from }} to {{ $to }}">
        <x-fetch :of="$period" what="Period revenue" />
        @if ($period->ok())
            @php $data = $period->data['data'] ?? []; @endphp
            @if (isset($data['total']))<p class="mb-3 text-sm">Total: <b><x-money :value="$data['total']" /></b></p>@endif
            @if (! empty($data['byFacility']))
                <div class="overflow-x-auto"><table class="data-table"><thead><tr><th>Facility</th><th class="text-right">Revenue</th></tr></thead><tbody>
                @foreach ($data['byFacility'] as $r)<tr><td>{{ $r['facility'] ?? $r['facilityId'] ?? '' }}</td><td class="text-right"><x-money :value="$r['revenue'] ?? '0'" /></td></tr>@endforeach
                </tbody></table></div>
            @elseif (empty($data['total']))
                <pre class="overflow-x-auto rounded-lg bg-stone-50 p-3 text-xs">{{ json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre>
            @endif
        @endif
    </x-card>
</x-layouts.app>
