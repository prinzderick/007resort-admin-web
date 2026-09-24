<x-layouts.app title="Tickets & entry">
    <x-page-header title="Tickets & entry" subtitle="Tickets, passes and equipment rentals that have been issued. Scanning and redemption happen at the gates and stores; QR codes are never shown here." />
    <x-card flush x-data="tableTools">
        <x-table-tools placeholder="Filter this page (holder, item...)">
            <form method="GET" class="flex items-end gap-3"><div><label class="mb-1 block text-xs font-medium text-stone-600">Status</label><select name="status" class="min-h-10 rounded-lg border border-stone-300 bg-white px-2 text-sm" onchange="this.form.submit()"><option value="">Any</option>@foreach (['ACTIVE', 'EXHAUSTED', 'EXPIRED', 'VOID'] as $s)<option @selected($status === $s)>{{ $s }}</option>@endforeach</select></div></form>
        </x-table-tools>
        <x-fetch :of="$entitlements" what="Tickets" />
        @if ($entitlements->ok())
            <div class="table-scroll"><table class="data-table" data-testid="entitlements">
                <thead><tr><th data-sort>Issued</th><th data-sort>Holder</th><th>Items</th><th>Valid until</th><th>Status</th><th></th></tr></thead>
                <tbody x-ref="body">
                @forelse ($items as $e)
                    @php $first = ($e['items'][0] ?? []); @endphp
                    <tr data-row><td data-sort="{{ $e['issuedAt'] ?? '' }}"><x-time :at="$e['issuedAt'] ?? null" /></td><td>{{ $e['holderName'] ?? '-' }}</td>
                        <td class="text-xs">@foreach ($e['items'] ?? [] as $i){{ $i['name'] ?? '' }} <span class="text-stone-500">({{ $i['kind'] ?? '' }}, {{ $i['quantityRedeemed'] ?? 0 }}/{{ $i['quantity'] ?? 0 }} used)</span><br>@endforeach</td>
                        <td><x-time :at="$first['validUntil'] ?? null" /></td><td><x-badge :status="$e['status'] ?? 'UNKNOWN'" /></td>
                        <td class="text-right"><x-detail :title="'Ticket for '.($e['holderName'] ?? 'guest')" :fields="collect($e['items'] ?? [])->map(fn ($i) => [($i['name'] ?? 'Item'), ($i['kind'] ?? '').' - '.str_replace('_', ' ', $i['validationMode'] ?? '').' - '.($i['quantityRedeemed'] ?? 0).'/'.($i['quantity'] ?? 0).' used at '.($facilityNames[$i['facilityId'] ?? ''] ?? '')])->prepend(['Status', $e['status'] ?? ''])->all()" /></td></tr>
                @empty<tr><td colspan="6"><x-empty title="No tickets issued" text="Tickets are issued when a booking or ticket order is paid." icon="ticket" /></td></tr>@endforelse
                </tbody></table></div>
            <x-pagination :count="count($entitlements->items())" :next="$entitlements->next()" />
        @endif
    </x-card>
    <x-pending-api :items="['Redemption history per gate (scan log) and tickets-sold report: the API exposes issued entitlements only', 'Ticket types (pool day pass, sports entry, rental) are managed under Setup > Ticket types']" />
</x-layouts.app>
