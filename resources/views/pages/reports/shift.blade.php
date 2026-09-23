<x-layouts.app title="Shift report">
    <x-page-header title="Cashier shift report" subtitle="Terminal, staff member and every transaction in the shift.">
        <x-slot:actions>
            <x-btn variant="secondary" :href="request()->fullUrlWithQuery(['format' => 'csv'])">Export transactions CSV</x-btn>
        </x-slot:actions>
    </x-page-header>
    <x-drill at="Terminal" />
    <x-freshness :freshness="$freshness" />
    <x-fetch :of="$report" what="Shift report" />
    @if ($report->ok())
        @php $r = $report->data; @endphp
        <div class="mb-5 grid grid-cols-2 gap-3 lg:grid-cols-4">
            <x-stat label="Cashier" :value="$r['staffName']" :hint="'Opened '.\App\Support\Time::format($r['openedAt'])" />
            <x-stat label="Opening float" :value="\App\Support\Money::format($r['openingFloat'])" />
            <x-stat label="Expected cash" :value="\App\Support\Money::format($r['expectedCash'])" />
            <x-stat label="Variance" :value="$r['variance'] !== null ? \App\Support\Money::format($r['variance']) : 'Open shift'" :tone="$r['variance'] !== null && \App\Support\Money::cmp($r['variance'], '0') !== 0 ? 'bad' : 'default'" :hint="$r['countedCash'] !== null ? 'Counted '.\App\Support\Money::format($r['countedCash']) : null" />
        </div>
        <x-card title="Takings by method" flush><div class="overflow-x-auto"><table class="data-table"><thead><tr><th>Method</th><th class="text-right">Count</th><th class="text-right">Amount</th></tr></thead><tbody>
            @foreach ($r['byTender'] as $t)<tr><td>{{ str_replace('_', ' ', $t['tenderType']) }}</td><td class="text-right">{{ $t['count'] }}</td><td class="text-right"><x-money :value="$t['amount']" /></td></tr>@endforeach
        </tbody></table></div>
        <p class="border-t border-stone-100 p-3 text-xs text-stone-600">Refunds {{ \App\Support\Money::format($r['refunds'] ?? '0') }} &middot; Voids {{ $r['voids'] ?? 0 }}</p></x-card>
    @endif
    <x-card title="Transactions" flush>
        <x-fetch :of="$payments" what="Transactions" />
        @if ($payments->ok())
            <div class="overflow-x-auto"><table class="data-table" data-testid="txn-table"><thead><tr><th>Time</th><th>Method</th><th>Status</th><th class="text-right">Amount</th><th></th></tr></thead><tbody>
            @forelse ($payments->items() as $p)
                <tr><td><x-time :at="$p['createdAt']" /></td><td>{{ str_replace('_', ' ', $p['tenderType']) }}</td><td><x-badge :status="$p['status']" /></td><td class="text-right"><x-money :value="$p['amount']" /></td>
                    <td><a class="underline" href="{{ route('finance.payment', $p['id']) }}">Open</a></td></tr>
            @empty<tr><td colspan="5" class="text-center text-stone-500">No transactions.</td></tr>@endforelse
            </tbody></table></div>
        @endif
    </x-card>
</x-layouts.app>
