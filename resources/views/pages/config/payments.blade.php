<x-layouts.app title="Payment timing">
    <x-page-header title="Payment timing & approval rules" subtitle="Per facility: when payment happens, when approvals are needed, what works offline." />
    <x-config-nav />
    <x-fetch :of="$tree" what="Facilities" />
    <x-card flush>
        <div class="overflow-x-auto"><table class="data-table" data-testid="rules-table"><thead><tr><th>Facility</th><th class="text-right">Approval above</th><th>Open tabs</th><th>Cash session</th><th>Offline orders</th><th>Offline payments</th><th>Payment timing</th></tr></thead><tbody>
        @forelse ($rules as $r)
            <tr><td class="font-medium"><a class="underline" href="{{ route('config.facilities', ['facility' => $r['facility']['id']]) }}">{{ $r['facility']['name'] }}</a></td><td class="text-right"><x-money :value="$r['rules']['approvalThresholdAmount'] ?? '0'" /></td>
                <td>{{ ($r['rules']['allowOpenTabs'] ?? false) ? 'Yes' : 'No' }}</td><td>{{ ($r['rules']['requireCashSession'] ?? false) ? 'Required' : 'No' }}</td><td>{{ ($r['rules']['allowOfflineOrders'] ?? false) ? 'Yes' : 'No' }}</td>
                <td>{{ $r['rules']['allowOfflinePayments'] ?? '-' }}</td><td>{{ $r['rules']['paymentTiming'] ?? 'not reported' }}</td></tr>
        @empty<tr><td colspan="7" class="text-center text-stone-500">No data.</td></tr>@endforelse
        </tbody></table></div>
    </x-card>
    <x-pending-api :items="['Editing these rules and the payment_timing rule (PAY_BEFORE / PAY_ON_EXIT) - the edit form on the facility page appears once PUT /facilities/{facilityId}/capabilities is in the contract', 'The contract does not yet expose payment timing in operatingRules']" />
</x-layouts.app>
