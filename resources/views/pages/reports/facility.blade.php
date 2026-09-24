<x-layouts.app title="Facility report">
    <x-page-header :title="($facility['name'] ?? 'Facility').' report'" :subtitle="'Day '.$date">
        <x-slot:actions>
            <x-btn variant="secondary" :href="route('reports.index', ['date' => $date])">Property</x-btn>
            <x-btn variant="secondary" :href="request()->fullUrlWithQuery(['format' => 'csv'])">Export CSV</x-btn>
        </x-slot:actions>
    </x-page-header>
    <x-drill at="Facility" />
    <x-freshness :freshness="$freshness" />
    <x-fetch :of="$summary" what="Facility summary" />

    @if ($summary->ok())
        @php $s = $summary->data; @endphp
        <div class="mb-5 grid grid-cols-2 gap-3 lg:grid-cols-4">
            <x-stat label="Net sales" :value="\App\Support\Money::format($s['netSales'] ?? '0')" />
            <x-stat label="Orders" :value="$s['orders'] ?? 0" />
            <x-stat label="Refunds" :value="\App\Support\Money::format($s['refunds'] ?? '0')" />
            <x-stat label="Voids" :value="($s['voids']['count'] ?? 0).' ('.\App\Support\Money::format($s['voids']['amount'] ?? '0').')'" />
        </div>
        <div class="grid gap-5 lg:grid-cols-2">
            <x-card title="Payments by method" flush><div class="table-scroll"><table class="data-table"><thead><tr><th>Method</th><th class="text-right">Count</th><th class="text-right">Amount</th></tr></thead><tbody>
                @foreach ($s['byTender'] ?? [] as $t)<tr><td>{{ str_replace('_', ' ', $t['tenderType'] ?? '') }}</td><td class="text-right tabular-nums">{{ $t['count'] ?? 0 }}</td><td class="text-right"><x-money :value="$t['amount'] ?? '0'" /></td></tr>@endforeach
            </tbody></table></div></x-card>
            <x-card title="Top products" flush><div class="table-scroll"><table class="data-table"><thead><tr><th>Product</th><th class="text-right">Qty</th><th class="text-right">Revenue</th></tr></thead><tbody>
                @foreach ($s['topProducts'] ?? [] as $p)<tr><td>{{ $p['name'] ?? '' }}</td><td class="text-right tabular-nums">{{ $p['quantity'] ?? 0 }}</td><td class="text-right"><x-money :value="$p['revenue'] ?? '0'" /></td></tr>@endforeach
            </tbody></table></div></x-card>
        </div>
    @endif

    <x-card title="Operating points" flush>
        <x-fetch :of="$points" what="Operating points" />
        @if ($points->ok())
            <div class="table-scroll"><table class="data-table"><thead><tr><th>Code</th><th>Name</th><th>Kind</th></tr></thead><tbody>
            @forelse ($points->items() as $p)<tr><td>{{ $p['code'] ?? '' }}</td><td>{{ $p['name'] ?? '' }}</td><td>{{ str_replace('_', ' ', $p['kind'] ?? '') }}</td></tr>@empty<tr><td colspan="3" class="text-center text-stone-500">None.</td></tr>@endforelse
            </tbody></table></div>
            <p class="border-t border-stone-100 p-3 text-xs text-stone-500">Revenue per operating point is not available yet: the API has no operating-point report, so totals below stop at terminal/shift level.</p>
        @endif
    </x-card>

    <x-card title="Terminals and shifts (cash sessions)" flush>
        <x-fetch :of="$sessions" what="Cash sessions" />
        @if ($sessions->ok())
            <div class="table-scroll"><table class="data-table" data-testid="shift-table"><thead><tr><th>Terminal</th><th>Opened</th><th>Status</th><th class="text-right">Expected cash</th><th class="text-right">Counted</th><th class="text-right">Variance</th><th></th></tr></thead><tbody>
            @forelse ($sessions->items() as $c)
                <tr><td>{{ $deviceNames[$c['deviceId'] ?? ''] ?? \Illuminate\Support\Str::limit($c['deviceId'] ?? 'unknown', 8, '') }}</td><td><x-time :at="$c['openedAt'] ?? null" /></td><td><x-badge :status="$c['status'] ?? 'UNKNOWN'" /></td>
                    <td class="text-right"><x-money :value="$c['expectedCash'] ?? '0'" /></td><td class="text-right">@if (($c['countedCash'] ?? null) !== null)<x-money :value="$c['countedCash']" />@else &mdash; @endif</td>
                    <td class="text-right">@if (($c['variance'] ?? null) !== null)<span class="{{ \App\Support\Money::cmp($c['variance'], '0') !== 0 ? 'font-semibold text-red-800' : '' }}"><x-money :value="$c['variance']" /></span>@else &mdash; @endif</td>
                    <td>@if (! empty($c['id']))<a class="underline" href="{{ route('reports.shift', $c['id']) }}">Shift report</a>@endif</td></tr>
            @empty<tr><td colspan="7" class="text-center text-stone-500">No shifts.</td></tr>@endforelse
            </tbody></table></div>
        @endif
    </x-card>
</x-layouts.app>
