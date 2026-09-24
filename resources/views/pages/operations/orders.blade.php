<x-layouts.app title="Orders">
    <x-page-header title="Orders" subtitle="Every order taken at the restaurants, bars, cafe and shops. Orders are created and changed on the tablets and POS; this is the live record." />
    <x-card flush x-data="tableTools">
        <x-table-tools :csv="true" placeholder="Filter this page (number, table...)">
            <form method="GET" class="flex flex-wrap items-end gap-3">
                <div><label class="mb-1 block text-xs font-medium text-stone-600">Facility</label><select name="facility" class="min-h-10 rounded-lg border border-stone-300 bg-white px-2 text-sm" onchange="this.form.submit()"><option value="">All facilities</option>@foreach ($facilities as $f)<option value="{{ $f['id'] ?? '' }}" @selected($facilityId === ($f['id'] ?? ''))>{{ $f['name'] ?? '' }}</option>@endforeach</select></div>
                <div><label class="mb-1 block text-xs font-medium text-stone-600">Status</label><select name="status" class="min-h-10 rounded-lg border border-stone-300 bg-white px-2 text-sm" onchange="this.form.submit()"><option value="">Any</option>@foreach (['DRAFT', 'SENT', 'SERVED', 'SETTLED', 'VOIDED', 'CANCELLED'] as $s)<option @selected($status === $s)>{{ $s }}</option>@endforeach</select></div>
            </form>
        </x-table-tools>
        <x-fetch :of="$orders" what="Orders" />
        @if ($orders->ok())
            <div class="table-scroll"><table class="data-table" data-testid="orders-table">
                <thead><tr><th data-sort>Order</th><th data-sort>Facility</th><th>Table</th><th data-sort>Status</th><th class="text-right">Lines</th><th class="text-right" data-sort>Total</th><th class="text-right">Balance due</th><th data-sort>Created</th><th></th></tr></thead>
                <tbody x-ref="body">
                @forelse ($orders->items() as $o)
                    <tr data-row><td class="font-medium">{{ $o['number'] ?? '' }}</td><td>{{ $facilityNames[$o['facilityId'] ?? ''] ?? '' }}</td><td>{{ $o['tableLabel'] ?? '-' }}</td><td><x-badge :status="$o['status'] ?? 'UNKNOWN'" /></td><td class="text-right tabular-nums">{{ $o['lineCount'] ?? 0 }}</td>
                        <td class="text-right" data-sort="{{ $o['total'] ?? 0 }}"><x-money :value="$o['total'] ?? '0'" /></td><td class="text-right"><x-money :value="$o['balanceDue'] ?? '0'" /></td><td data-sort="{{ $o['createdAt'] ?? '' }}"><x-time :at="$o['createdAt'] ?? null" ago /></td>
                        <td class="text-right">@if (! empty($o['id']))<x-detail :title="'Order '.($o['number'] ?? '')" :href="route('orders.show', $o['id'])" link-label="Open order" :fields="[['Status', $o['status'] ?? ''], ['Facility', $facilityNames[$o['facilityId'] ?? ''] ?? ''], ['Table', $o['tableLabel'] ?? '-'], ['Lines', $o['lineCount'] ?? 0], ['Total', \App\Support\Money::format($o['total'] ?? '0')], ['Balance due', \App\Support\Money::format($o['balanceDue'] ?? '0')], ['Created', \App\Support\Time::format($o['createdAt'] ?? null)]]" />@endif</td></tr>
                @empty<tr><td colspan="9"><x-empty title="No orders match" text="Try another facility or status. Orders appear here as soon as a waiter or cashier starts one." icon="cart" /></td></tr>@endforelse
                </tbody></table></div>
            <x-pagination :count="count($orders->items())" :next="$orders->next()" />
        @endif
    </x-card>
</x-layouts.app>
