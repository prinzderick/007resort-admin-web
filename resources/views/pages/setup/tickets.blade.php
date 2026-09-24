<x-layouts.app title="Ticket types">
    <x-page-header title="Ticket types & entitlements" subtitle="Tickets, rentals and access passes that have been issued (read-only)." />
    <x-card title="Issued entitlements" flush>
        <x-fetch :of="$entitlements" what="Entitlements" />
        @if ($entitlements->ok())
            <div class="overflow-x-auto"><table class="data-table" data-testid="entitlements"><thead><tr><th>Issued</th><th>Holder</th><th>Items</th><th>Status</th></tr></thead><tbody>
            @forelse ($entitlements->items() as $e)
                <tr><td><x-time :at="$e['issuedAt'] ?? null" /></td><td>{{ $e['holderName'] ?? '' }}</td>
                    <td class="text-xs">@foreach ($e['items'] ?? [] as $i){{ $i['name'] ?? '' }} <span class="text-stone-500">({{ $i['kind'] ?? '' }}, {{ str_replace('_', ' ', $i['validationMode'] ?? '') }}, {{ $i['quantityRedeemed'] ?? 0 }}/{{ $i['quantity'] ?? 0 }} used)</span><br>@endforeach</td>
                    <td><x-badge :status="$e['status'] ?? 'UNKNOWN'" /></td></tr>
            @empty<tr><td colspan="4" class="text-center text-stone-500">None issued.</td></tr>@endforelse
            </tbody></table></div>
            @if ($entitlements->next())<div class="p-3"><x-btn variant="secondary" :href="request()->fullUrlWithQuery(['cursor' => $entitlements->next()])">Next page</x-btn></div>@endif
        @endif
    </x-card>
    @unless ($typesEndpoint)
        <x-pending-api :items="['List / create / edit ticket types (pool day pass, sports entry, rentals) with validity, validation mode and pricing: the API has no /ticket-types endpoint yet (types exist in its database and are used when tickets are issued)', 'Validation mode ENTRY vs ENTRY_EXIT and validity windows as editable rules']" />
    @endunless
</x-layouts.app>
