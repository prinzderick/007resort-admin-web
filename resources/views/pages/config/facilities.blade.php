<x-layouts.app title="Facilities">
    <x-page-header title="Facilities & operating points" />
    <x-config-nav />
    <div class="grid gap-5 lg:grid-cols-3">
        <x-card title="Facility tree">
            <x-fetch :of="$tree" what="Facilities" />
            @if ($tree->ok())@include('partials.facility-tree', ['nodes' => $tree->items(), 'selected' => $selected])@endif
        </x-card>
        <div class="lg:col-span-2">
            @if ($detail)
                <x-fetch :of="$detail" what="Capabilities" />
                @if ($detail->ok())
                    @php $r = $detail->data['operatingRules'] ?? []; @endphp
                    <x-card title="Capabilities">
                        <div class="flex flex-wrap gap-2">@forelse ($detail->data['capabilities'] as $c)<x-badge tone="info">{{ $c }}</x-badge>@empty<span class="text-sm text-stone-500">None</span>@endforelse</div>
                    </x-card>
                    <x-card title="Operating rules" data-testid="rules">
                        @if ($canWrite && auth_staff()->canAny('facility.configure', 'config.manage'))
                            <form method="POST" action="{{ route('config.facilities.rules', $selected) }}">
                                @csrf @method('PUT')
                                <x-field name="approvalThresholdAmount" label="Approval threshold (NGN)" :value="$r['approvalThresholdAmount'] ?? '0'" required />
                                <x-field name="allowOfflinePayments" label="Offline payments" :options="['NONE' => 'None', 'CASH_ONLY' => 'Cash only', 'ALL' => 'All']" :value="$r['allowOfflinePayments'] ?? 'NONE'" required />
                                <x-field name="paymentTiming" label="Payment timing" :options="['' => '(unchanged)', 'PAY_BEFORE' => 'Pay before service', 'PAY_ON_EXIT' => 'Pay on exit', 'PAY_LATER' => 'Pay later (tab)']" :value="$r['paymentTiming'] ?? ''" />
                                @foreach (['allowOpenTabs' => 'Allow open tabs', 'requireCashSession' => 'Require an open cash session', 'allowOfflineOrders' => 'Allow orders while offline'] as $k => $l)
                                    <label class="mb-2 flex min-h-11 items-center gap-2 text-sm"><input type="checkbox" name="{{ $k }}" value="1" @checked($r[$k] ?? false)> {{ $l }}</label>
                                @endforeach
                                <x-btn>Save rules</x-btn>
                            </form>
                        @else
                            <dl class="grid gap-x-6 gap-y-2 text-sm sm:grid-cols-2">
                                <div><dt class="text-stone-500">Approval threshold</dt><dd><x-money :value="$r['approvalThresholdAmount'] ?? '0'" /></dd></div>
                                <div><dt class="text-stone-500">Approval required for</dt><dd>{{ implode(', ', $r['requireApprovalFor'] ?? []) ?: '-' }}</dd></div>
                                <div><dt class="text-stone-500">Open tabs</dt><dd>{{ ($r['allowOpenTabs'] ?? false) ? 'Allowed' : 'Not allowed' }}</dd></div>
                                <div><dt class="text-stone-500">Cash session required</dt><dd>{{ ($r['requireCashSession'] ?? false) ? 'Yes' : 'No' }}</dd></div>
                                <div><dt class="text-stone-500">Offline orders</dt><dd>{{ ($r['allowOfflineOrders'] ?? false) ? 'Allowed' : 'No' }}</dd></div>
                                <div><dt class="text-stone-500">Offline payments</dt><dd>{{ $r['allowOfflinePayments'] ?? '-' }}</dd></div>
                                <div><dt class="text-stone-500">Hold time (bookings)</dt><dd>{{ $r['holdTtlSeconds'] ?? '-' }} s</dd></div>
                                <div><dt class="text-stone-500">VAT</dt><dd>{{ ($r['vatEnabled'] ?? false) ? ($r['vatRatePercent'] ?? '').'%' : 'Off' }}</dd></div>
                            </dl>
                            <p class="mt-3 text-xs text-stone-500">Editing rules is not in the API contract yet (needs <code>PUT /facilities/{facilityId}/capabilities</code>), so these are read-only.</p>
                        @endif
                    </x-card>
                @endif
                <x-card title="Operating points" flush>
                    <x-fetch :of="$points" what="Operating points" />
                    @if ($points->ok())<div class="overflow-x-auto"><table class="data-table"><thead><tr><th>Code</th><th>Name</th><th>Kind</th></tr></thead><tbody>@forelse ($points->items() as $p)<tr><td>{{ $p['code'] }}</td><td>{{ $p['name'] }}</td><td>{{ str_replace('_', ' ', $p['kind']) }}</td></tr>@empty<tr><td colspan="3" class="text-center text-stone-500">None</td></tr>@endforelse</tbody></table></div>@endif
                </x-card>
            @else
                <x-card><p class="text-sm text-stone-600">Select a facility to see its capabilities, operating rules and operating points.</p></x-card>
            @endif
            <x-pending-api :items="['Create / rename / deactivate facilities and operating points', 'Edit capabilities (enable/disable) per facility']" />
        </div>
    </div>
</x-layouts.app>
