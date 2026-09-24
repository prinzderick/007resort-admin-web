<x-layouts.app title="Refunds & reversals" :range="true">
    <x-page-header title="Refunds & reversals" subtitle="Money handed back or corrected. Refunds and reversals are ledger records, never edits; big ones wait for a second person to approve.">
        <x-slot:actions><x-btn variant="secondary" icon="download" :href="request()->fullUrlWithQuery(['format' => 'csv'])">CSV</x-btn>@if (auth_staff()->canApproveAnything())<x-btn variant="secondary" :href="route('approvals')">Approvals queue</x-btn>@endif</x-slot:actions>
    </x-page-header>
    @if ($pendingFetch->ok() && $waiting !== [])
        <x-card title="Waiting for approval" :subtitle="count($waiting).' request(s) - no money has moved yet'" data-testid="waiting-approval">
            <ul class="divide-y divide-stone-100 text-sm">@foreach ($waiting as $a)<li class="flex flex-wrap items-center justify-between gap-2 py-2"><span><b>{{ $a['summary'] ?? $a['action'] ?? '' }}</b> <span class="text-stone-500">requested by {{ $a['requestedByName'] ?? 'staff' }}</span></span><span class="text-xs text-stone-500">{{ $a['reason'] ?? '' }}</span></li>@endforeach</ul>
        </x-card>
    @endif
    <x-card flush x-data="tableTools">
        <x-table-tools placeholder="Filter this page..." />
        <x-fetch :of="$fetch" what="Refunds" />
        @if ($fetch->ok())
            <div class="table-scroll"><table class="data-table" data-testid="refunds-table">
                <thead><tr><th data-sort>Payment date</th><th>Facility</th><th>Method</th><th>Status</th><th class="text-right" data-sort>Original</th><th class="text-right" data-sort>Refunded</th><th></th></tr></thead>
                <tbody x-ref="body">
                @forelse ($rows as $p)
                    <tr data-row><td data-sort="{{ $p['createdAt'] ?? '' }}"><x-time :at="$p['createdAt'] ?? null" /></td><td>{{ $facilityNames[$p['facilityId'] ?? ''] ?? '' }}</td><td>{{ str_replace('_', ' ', $p['tenderType'] ?? '') }}</td><td><x-badge :status="$p['status'] ?? 'UNKNOWN'" /></td>
                        <td class="text-right" data-sort="{{ $p['amount'] ?? 0 }}"><x-money :value="$p['amount'] ?? '0'" /></td><td class="text-right" data-sort="{{ $p['refundedAmount'] ?? 0 }}"><x-money :value="$p['refundedAmount'] ?? '0'" /></td>
                        <td>@if (! empty($p['id']))<a class="underline" href="{{ route('finance.payment', $p['id']) }}">Open</a>@endif</td></tr>
                @empty<tr><td colspan="7"><x-empty title="No refunds or reversals in this period" text="Widen the date range with the picker at the top." icon="undo" /></td></tr>@endforelse
                </tbody></table></div>
        @endif
    </x-card>
</x-layouts.app>
