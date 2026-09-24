<x-layouts.app title="Reconciliation">
    <x-page-header title="Settlement reconciliation" subtitle="Payments the API reports, grouped for matching against provider and bank settlements.">
        <x-slot:actions><x-btn variant="secondary" :href="route('finance.payments')">All payments</x-btn></x-slot:actions>
    </x-page-header>
    <form method="GET" class="mb-5 flex flex-wrap items-end gap-3">
        <div><label class="mb-1 block text-sm font-medium">From</label><input type="date" name="from" value="{{ $from }}" class="min-h-11 rounded-lg border border-stone-300 px-3 text-sm"></div>
        <div><label class="mb-1 block text-sm font-medium">To</label><input type="date" name="to" value="{{ $to }}" class="min-h-11 rounded-lg border border-stone-300 px-3 text-sm"></div>
        <x-btn variant="secondary">Apply</x-btn>
    </form>
    <x-fetch :of="$payments" what="Payments" />
    @if ($payments->ok())
        <x-card title="Captured by method" flush><div class="overflow-x-auto"><table class="data-table" data-testid="recon-methods"><thead><tr><th>Method</th><th class="text-right">Payments</th><th class="text-right">Captured</th><th class="text-right">Refunded</th><th class="text-right">Net</th></tr></thead><tbody>
            @forelse ($byMethod as $m => $v)<tr><td>{{ str_replace('_', ' ', $m) }}</td><td class="text-right">{{ $v['count'] }}</td><td class="text-right"><x-money :value="$v['captured']" /></td><td class="text-right"><x-money :value="$v['refunded']" /></td><td class="text-right"><x-money :value="\App\Support\Money::sub($v['captured'], $v['refunded'])" /></td></tr>@empty<tr><td colspan="5" class="text-center text-stone-500">No payments in range.</td></tr>@endforelse
        </tbody></table></div></x-card>

        <x-card title="Provider transactions to match" flush>
            @if ($unsettled !== [])<div class="border-b border-amber-300 bg-amber-50 px-4 py-2 text-sm text-amber-950" data-testid="unsettled">{{ count($unsettled) }} provider payment(s) are not captured. Verify them against the provider before closing the day.</div>@endif
            <div class="overflow-x-auto"><table class="data-table"><thead><tr><th>Reference</th><th>Provider</th><th>Status</th><th class="text-right">Amount</th><th></th></tr></thead><tbody>
            @forelse ($provider as $p)
                <tr><td class="text-xs">{{ $p['providerReference'] ?? '' }}</td><td>{{ $p['provider'] ?? '' }}</td><td><x-badge :status="$p['status'] ?? 'UNKNOWN'" /></td><td class="text-right"><x-money :value="$p['amount'] ?? '0'" /></td>
                    <td><form method="POST" action="{{ route('finance.paystack-verify') }}">@csrf<input type="hidden" name="reference" value="{{ $p['providerReference'] ?? '' }}"><button class="min-h-10 rounded-lg border border-stone-300 px-3 text-sm hover:bg-stone-50">Verify with Paystack</button></form></td></tr>
            @empty<tr><td colspan="5" class="text-center text-stone-500">No provider payments.</td></tr>@endforelse
            </tbody></table></div>
        </x-card>
    @endif
    <x-pending-api :items="['Bank settlement file / payout import and automatic matching (no settlement endpoints in the contract yet)', 'Recording a reconciliation sign-off (settlement.reconcile) against a day or payout']" />
</x-layouts.app>
