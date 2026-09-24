<x-layouts.app title="Payment timing">
    <x-page-header title="Payment timing & approval rules" subtitle="Per facility: when payment happens, when approvals are needed, what works offline." />
    <x-fetch :of="$tree" what="Facilities" />
    <x-card flush x-data="tableTools">
        <x-table-tools />
        <div class="table-scroll"><table class="data-table" data-testid="rules-table"><thead><tr><th>Facility</th><th class="text-right">Approval above</th><th>Open tabs</th><th>Cash session</th><th>Offline orders</th><th>Offline payments</th><th>Payment timing</th></tr></thead><tbody>
        @forelse ($rules as $r)
            <tr data-row><td class="font-medium"><a class="underline" href="{{ route('setup.facilities', ['facility' => $r['facility']['id'] ?? '']) }}">{{ $r['facility']['name'] ?? '' }}</a></td><td class="text-right"><x-money :value="$r['rules']['approvalThresholdAmount'] ?? '0'" /></td>
                <td>{{ ($r['rules']['allowOpenTabs'] ?? false) ? 'Yes' : 'No' }}</td><td>{{ ($r['rules']['requireCashSession'] ?? false) ? 'Required' : 'No' }}</td><td>{{ ($r['rules']['allowOfflineOrders'] ?? false) ? 'Yes' : 'No' }}</td>
                <td>{{ $r['rules']['allowOfflinePayments'] ?? '-' }}</td><td>{{ isset($r['rules']['paymentTiming']) ? str_replace('_', ' ', $r['rules']['paymentTiming']) : 'not reported' }}</td></tr>
        @empty<tr><td colspan="7" class="text-center text-stone-500">No data.</td></tr>@endforelse
        </tbody></table></div>
    </x-card>
    <x-pending-api :items="['Editing these rules (approval threshold, open tabs, cash session, offline policy, payment timing): the API has no endpoint that writes operating rules yet. The edit form on the facility page appears once PUT /facilities/{facilityId}/capabilities is in the contract']" />
</x-layouts.app>
