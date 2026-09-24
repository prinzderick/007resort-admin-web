<x-layouts.app title="Payments">
    <x-page-header title="Finance" subtitle="Payments, refunds and reversals. Sensitive actions may wait for approval.">
        <x-slot:actions>
            <x-btn variant="secondary" :href="route('finance.reconciliation')">Settlement reconciliation</x-btn>
            @if (auth_staff()->canApproveAnything())<x-btn variant="secondary" :href="route('approvals')">Approvals</x-btn>@endif
            <x-btn variant="secondary" :href="request()->fullUrlWithQuery(['format' => 'csv'])">Export CSV</x-btn>
        </x-slot:actions>
    </x-page-header>

    <form method="GET" class="mb-5 flex flex-wrap items-end gap-3">
        <div><label class="mb-1 block text-sm font-medium">Status</label>
            <select name="filter[status]" class="min-h-11 rounded-lg border border-stone-300 bg-white px-3 text-sm"><option value="">Any</option>@foreach (['INITIATED', 'AUTHORIZING', 'CAPTURED', 'FAILED', 'CANCELLED', 'PARTIALLY_REFUNDED', 'REFUNDED', 'REVERSED'] as $s)<option value="{{ $s }}" @selected(($filter['status'] ?? '') === $s)>{{ $s }}</option>@endforeach</select></div>
        <div><label class="mb-1 block text-sm font-medium">Facility</label>
            <select name="filter[facilityId]" class="min-h-11 rounded-lg border border-stone-300 bg-white px-3 text-sm"><option value="">All</option>
            @foreach (app(\App\Services\Portal\DashboardData::class)->flatten($facilities->items()) as $f)<option value="{{ $f['id'] ?? '' }}" @selected(($filter['facilityId'] ?? '') === ($f['id'] ?? ''))>{{ $f['name'] ?? '' }}</option>@endforeach</select></div>
        <div><label class="mb-1 block text-sm font-medium">Method</label>
            <select name="filter[tenderType]" class="min-h-11 rounded-lg border border-stone-300 bg-white px-3 text-sm"><option value="">Any</option>@foreach (['CASH', 'CARD', 'TRANSFER', 'POS_TERMINAL'] as $t)<option value="{{ $t }}" @selected(($filter['tenderType'] ?? '') === $t)>{{ str_replace('_', ' ', $t) }}</option>@endforeach</select></div>
        <div><label class="mb-1 block text-sm font-medium">From</label><input type="date" name="filter[from]" value="{{ $filter['from'] ?? '' }}" class="min-h-11 rounded-lg border border-stone-300 px-3 text-sm"></div>
        <div><label class="mb-1 block text-sm font-medium">To</label><input type="date" name="filter[to]" value="{{ $filter['to'] ?? '' }}" class="min-h-11 rounded-lg border border-stone-300 px-3 text-sm"></div>
        <x-btn variant="secondary">Filter</x-btn>
    </form>

    <x-card flush x-data="tableTools">
        <x-table-tools :csv="true" />
        <x-fetch :of="$payments" what="Payments" />
        @if ($payments->ok())
            <div class="table-scroll"><table class="data-table" data-testid="payments-table">
                <thead><tr><th>Created</th><th>Method</th><th>Reference</th><th>Status</th><th class="text-right">Amount</th><th class="text-right">Refunded</th><th></th></tr></thead>
                <tbody>
                @forelse ($payments->items() as $p)
                    <tr data-row><td><x-time :at="$p['createdAt'] ?? null" /></td><td>{{ str_replace('_', ' ', $p['tenderType'] ?? '') }}@if (($p['provider'] ?? 'MANUAL') !== 'MANUAL')<div class="text-xs text-stone-500">{{ $p['provider'] }}</div>@endif</td>
                        <td class="text-xs">{{ $p['providerReference'] ?? $p['reference'] ?? '' }}</td><td><x-badge :status="$p['status'] ?? 'UNKNOWN'" /></td>
                        <td class="text-right"><x-money :value="$p['amount'] ?? '0'" /></td><td class="text-right"><x-money :value="$p['refundedAmount'] ?? '0'" /></td>
                        <td>@if (! empty($p['id']))<a class="underline" href="{{ route('finance.payment', $p['id']) }}">Open</a>@endif</td></tr>
                @empty<tr><td colspan="7" class="text-center text-stone-500">No payments match.</td></tr>@endforelse
                </tbody></table></div>
            <x-pagination :count="count($payments->items())" :next="$payments->next()" />
        @endif
    </x-card>
</x-layouts.app>
