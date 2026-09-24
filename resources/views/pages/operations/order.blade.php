<x-layouts.app :title="'Order '.($order->ok() ? ($order->data['number'] ?? '') : '')">
    <x-page-header :title="'Order '.($order->ok() ? ($order->data['number'] ?? '') : '')" :crumbs="['Orders' => route('orders.index'), 'Order' => null]">
        <x-slot:actions><x-btn variant="secondary" :href="route('orders.index')">All orders</x-btn></x-slot:actions>
    </x-page-header>
    <x-fetch :of="$order" what="Order" />
    @if ($order->ok())
        @php $o = (array) $order->data; @endphp
        <div class="mb-5 grid grid-cols-2 gap-3 lg:grid-cols-4">
            <x-stat label="Status" :value="$o['status'] ?? '-'" />
            <x-stat label="Total" :value="\App\Support\Money::format($o['total'] ?? '0')" :hint="'Subtotal '.\App\Support\Money::format($o['subtotal'] ?? '0').' &middot; tax '.\App\Support\Money::format($o['taxTotal'] ?? '0')" />
            <x-stat label="Paid" :value="\App\Support\Money::format($o['paid'] ?? '0')" />
            <x-stat label="Balance due" :value="\App\Support\Money::format($o['balanceDue'] ?? '0')" :tone="\App\Support\Money::cmp($o['balanceDue'] ?? '0', '0') > 0 ? 'warn' : 'default'" />
        </div>
        <x-card title="Lines" flush>
            <div class="table-scroll"><table class="data-table"><thead><tr><th>Item</th><th class="text-right">Qty</th><th class="text-right">Unit price</th><th class="text-right">Line total</th><th>Status</th><th>Adjustments</th></tr></thead><tbody>
            @forelse ($o['lines'] ?? [] as $l)
                <tr><td>{{ $l['name'] ?? '' }}@if (! empty($l['notes']))<div class="text-xs text-stone-500">{{ $l['notes'] }}</div>@endif</td><td class="text-right">{{ $l['quantity'] ?? '' }}</td><td class="text-right"><x-money :value="$l['unitPrice'] ?? '0'" /></td><td class="text-right"><x-money :value="$l['lineTotal'] ?? '0'" /></td><td><x-badge :status="$l['status'] ?? 'UNKNOWN'" /></td>
                    <td class="text-xs">@foreach ($l['adjustments'] ?? [] as $a){{ is_array($a) ? ($a['kind'] ?? '').' '.($a['value'] ?? $a['amount'] ?? '') : $a }}<br>@endforeach</td></tr>
            @empty<tr><td colspan="6" class="text-center text-stone-500">No lines.</td></tr>@endforelse
            </tbody></table></div>
        </x-card>
        <div class="grid gap-5 lg:grid-cols-2">
            <x-card title="Details"><dl class="grid gap-x-6 gap-y-2 text-sm sm:grid-cols-2">
                <div><dt class="text-stone-500">Facility</dt><dd>{{ $facilityNames[$o['facilityId'] ?? ''] ?? '' }}</dd></div>
                <div><dt class="text-stone-500">Channel</dt><dd>{{ $o['channel'] ?? '-' }}</dd></div>
                <div><dt class="text-stone-500">Customer</dt><dd>{{ $o['customerName'] ?? '-' }}</dd></div>
                <div><dt class="text-stone-500">Discounts</dt><dd><x-money :value="$o['discountTotal'] ?? '0'" /></dd></div>
            </dl></x-card>
            <x-card title="Payments on this order" flush>
                <x-fetch :of="$payments" what="Payments" />
                @if ($payments->ok())
                    <div class="table-scroll"><table class="data-table"><thead><tr><th>When</th><th>Method</th><th>Status</th><th class="text-right">Amount</th><th></th></tr></thead><tbody>
                    @forelse ($payments->items() as $p)<tr><td><x-time :at="$p['createdAt'] ?? null" /></td><td>{{ str_replace('_', ' ', $p['tenderType'] ?? '') }}</td><td><x-badge :status="$p['status'] ?? 'UNKNOWN'" /></td><td class="text-right"><x-money :value="$p['amount'] ?? '0'" /></td><td>@if (! empty($p['id']))<a class="underline" href="{{ route('finance.payment', $p['id']) }}">Open</a>@endif</td></tr>@empty<tr><td colspan="5" class="text-center text-stone-500">No payments yet.</td></tr>@endforelse
                    </tbody></table></div>
                @endif
            </x-card>
        </div>
    @endif
</x-layouts.app>
