<x-layouts.app title="Payment">
    <x-page-header title="Payment" :subtitle="$id">
        <x-slot:actions><x-btn variant="secondary" :href="route('finance.payments')">All payments</x-btn></x-slot:actions>
    </x-page-header>
    <x-fetch :of="$payment" what="Payment" />
    @if ($payment->ok())
        @php
            $p = $payment->data;
            $captured = in_array($p['status'], ['CAPTURED', 'PARTIALLY_REFUNDED'], true);
            $refundable = \App\Support\Money::sub($p['amount'], $p['refundedAmount'] ?? '0');
            $staff = auth_staff();
        @endphp
        <div class="mb-5 grid grid-cols-2 gap-3 lg:grid-cols-4">
            <x-stat label="Amount" :value="\App\Support\Money::format($p['amount'])" />
            <x-stat label="Refunded" :value="\App\Support\Money::format($p['refundedAmount'] ?? '0')" />
            <x-stat label="Status" :value="$p['status']" :tone="$p['status'] === 'CAPTURED' ? 'good' : 'warn'" />
            <x-stat label="Method" :value="str_replace('_', ' ', $p['tenderType'])" :hint="$p['providerReference'] ?? null" />
        </div>

        <div class="grid gap-5 lg:grid-cols-2">
            @if ($staff->can('refund.execute'))
                <x-card title="Refund">
                    @if ($captured && \App\Support\Money::cmp($refundable, '0') > 0)
                        <form method="POST" action="{{ route('finance.refund', $id) }}">
                            @csrf
                            <x-field name="amount" label="Amount (max {{ \App\Support\Money::format($refundable) }})" :value="$refundable" required hint="Full or partial. Over the facility threshold this waits for approval." />
                            <x-field name="reason" label="Reason" type="textarea" required />
                            <x-btn variant="danger" onclick="return confirm('Submit this refund?')">Request refund</x-btn>
                        </form>
                    @else
                        <p class="text-sm text-stone-600">This payment cannot be refunded (status {{ $p['status'] }}).</p>
                    @endif
                </x-card>
            @endif
            @if ($staff->can('payment.reversal.execute'))
                <x-card title="Reverse (same-session correction)">
                    @if ($p['status'] === 'CAPTURED')
                        <form method="POST" action="{{ route('finance.reversal', $id) }}">
                            @csrf
                            <x-field name="reason" label="Reason" type="textarea" required />
                            <x-btn variant="danger" onclick="return confirm('Reverse this payment?')">Request reversal</x-btn>
                        </form>
                    @else
                        <p class="text-sm text-stone-600">Only captured payments can be reversed.</p>
                    @endif
                </x-card>
            @endif
        </div>

        <x-card title="Details">
            <dl class="grid gap-x-6 gap-y-2 text-sm sm:grid-cols-2">
                <div><dt class="text-stone-500">Created</dt><dd><x-time :at="$p['createdAt']" /></dd></div>
                <div><dt class="text-stone-500">Captured</dt><dd><x-time :at="$p['capturedAt'] ?? null" /></dd></div>
                <div><dt class="text-stone-500">Receipt</dt><dd class="text-xs">{{ $p['receiptId'] ?? 'none' }}</dd></div>
                <div><dt class="text-stone-500">Cash session</dt><dd>@if ($p['cashSessionId'] ?? null)<a class="underline" href="{{ route('reports.shift', $p['cashSessionId']) }}">Shift report</a>@else none @endif</dd></div>
            </dl>
            <table class="data-table mt-3"><thead><tr><th>Order</th><th class="text-right">Allocated</th></tr></thead><tbody>@foreach ($p['allocations'] ?? [] as $a)<tr><td class="text-xs">{{ $a['orderId'] }}</td><td class="text-right"><x-money :value="$a['amount']" /></td></tr>@endforeach</tbody></table>
        </x-card>
    @endif
</x-layouts.app>
