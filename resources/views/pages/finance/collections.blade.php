<x-layouts.app title="Collected by waiters">
    <x-page-header title="Collected by waiters" subtitle="Money waiters took at the table. Nothing counts as paid until a cashier or supervisor checks it against the slip, the bank alert or the cash and confirms it here.">
        <x-slot:actions><x-btn variant="secondary" :href="route('finance.handovers')" icon="cash">Cash handovers</x-btn></x-slot:actions>
    </x-page-header>

    <div class="mb-5 grid gap-4 sm:grid-cols-3" data-testid="collection-stats">
        <x-stat-card label="Waiting for confirmation" :value="(string) count(array_filter($rows, fn ($p) => ($p['status'] ?? '') === 'PENDING_CONFIRMATION'))" hint="on this page" />
        <x-stat-card label="Amount waiting" :value="\App\Support\Money::format($pendingTotal)" hint="not yet counted as paid" />
        <x-stat-card label="Oldest first" value="{{ collect($rows)->pluck('createdAt')->filter()->sort()->first() ? \App\Support\Time::ago(collect($rows)->pluck('createdAt')->filter()->sort()->first()) : '-' }}" hint="collections expire on their own if nobody acts" />
    </div>

    <x-filter-form :reset="route('finance.collections')">
        <x-filter-select width="16rem" name="filter[status]" label="Show" :options="$statuses" :value="$status" all="Waiting for confirmation" />
        <x-filter-select width="13rem" name="filter[facilityId]" label="Facility" :options="collect($facilities)->pluck('name', 'id')->all()" :value="$filter['facilityId'] ?? null" all="All facilities" />
        <x-filter-select width="13rem" name="filter[tenderType]" label="How it was paid" :options="$tenders" :value="$filter['tenderType'] ?? null" all="Any" />
        <x-filter-select name="filter[collectedBy]" label="Waiter" :options="$staffNames" :value="$filter['collectedBy'] ?? null" all="Any waiter" />
    </x-filter-form>

    <x-card flush x-data="tableTools">
        <x-fetch :of="$payments" what="Collections" />
        @if ($payments->ok())
            <div class="table-scroll"><table class="data-table" data-testid="collections-table">
                <thead><tr><th>Collected</th><th>Bill</th><th>Waiter</th><th>How</th><th>Reference to check</th><th>Status</th><th class="num">Amount</th><th class="w-44"></th></tr></thead>
                <tbody>
                @forelse ($rows as $p)
                    @php
                        $c = $p['collection'] ?? [];
                        $oid = $p['allocations'][0]['orderId'] ?? null;
                        $o = $orders[$oid] ?? [];
                        $ref = $c['approvalCode'] ?? $c['slipReference'] ?? $c['bankReference'] ?? null;
                        $tender = $c['tender'] ?? $p['tenderType'] ?? '';
                        $pending = ($p['status'] ?? '') === 'PENDING_CONFIRMATION';
                        $mine = ($c['collectedByStaffId'] ?? null) === $myId;
                        $expires = \App\Support\Time::parse($c['expiresAt'] ?? null);
                        $waiter = $staffNames[$c['collectedByStaffId'] ?? ''] ?? null;
                    @endphp
                    <tr data-row>
                        <td class="whitespace-nowrap"><x-time :at="$p['createdAt'] ?? null" />@if ($pending && $expires)<div class="text-xs {{ $expires->isPast() ? 'text-red-700' : 'text-stone-500' }}">{{ $expires->isPast() ? 'expiring now' : 'expires '.$expires->diffForHumans() }}</div>@endif</td>
                        <td class="whitespace-nowrap"><div class="font-medium">{{ $o['number'] ?? 'Order' }}</div><div class="text-xs text-stone-500">{{ ($tableLabels[$o['tableId'] ?? ''] ?? null) ? 'Table '.$tableLabels[$o['tableId']] : ($facilityNames[$p['facilityId'] ?? ''] ?? '') }}@if (! empty($o['tableId']) && ! empty($tableLabels[$o['tableId']])) &middot; {{ $facilityNames[$p['facilityId'] ?? ''] ?? '' }}@endif</div></td>
                        <td class="whitespace-nowrap">{{ $waiter ?? 'Waiter '.\App\Services\Portal\Directory::short($c['collectedByStaffId'] ?? null) }}</td>
                        <td class="whitespace-nowrap">{{ $tenders[$tender] ?? $tender }}@if (! empty($c['last4']))<div class="text-xs text-stone-500">card ending {{ $c['last4'] }}</div>@endif</td>
                        <td class="font-mono text-xs">{{ $ref ?? ($tender === 'CASH' ? 'Count the cash' : '-') }}</td>
                        <td><x-badge :status="$p['status'] ?? 'UNKNOWN'">{{ ['PENDING_CONFIRMATION' => 'Waiting', 'AUTHORIZING' => 'With provider', 'CAPTURED' => 'Confirmed'][$p['status'] ?? ''] ?? ucfirst(strtolower($p['status'] ?? '')) }}</x-badge>@if (! empty($c['decisionReason']))<div class="mt-0.5 max-w-48 text-xs text-stone-500">{{ $c['decisionReason'] }}</div>@endif</td>
                        <td class="num"><x-money :value="$p['amount'] ?? '0'" /></td>
                        <td class="text-right">
                            @if ($pending && $canConfirm)
                                @if ($mine)<span class="text-xs text-stone-500" title="Someone else must confirm money you collected yourself">You collected this</span>
                                @elseif (! empty($c['autoConfirm']))<span class="text-xs text-stone-500">Confirms automatically</span>
                                @else
                                    <div class="flex justify-end gap-2">
                                        <x-btn type="button" variant="secondary" @click="$dispatch('open-modal', { name: 'reject-collection', data: {{ \Illuminate\Support\Js::from(['id' => $p['id'], 'amount' => \App\Support\Money::format($p['amount'] ?? '0')]) }} })" data-testid="reject-btn">Reject</x-btn>
                                        <x-btn type="button" @click="$dispatch('open-modal', { name: 'confirm-collection', data: {{ \Illuminate\Support\Js::from(['id' => $p['id'], 'amount' => \App\Support\Money::format($p['amount'] ?? '0'), 'tender' => $tender, 'tenderLabel' => $tenders[$tender] ?? $tender, 'order' => $o['number'] ?? 'this bill', 'ref' => $ref]) }} })" data-testid="confirm-btn">Confirm</x-btn>
                                    </div>
                                @endif
                            @elseif (! empty($p['id']))<a class="text-sm font-medium text-brand-700 underline" href="{{ route('finance.payment', $p['id']) }}">Open</a>@endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="8"><x-empty title="{{ $status === 'PENDING_CONFIRMATION' ? 'Nothing is waiting' : 'No collections match' }}" text="{{ $status === 'PENDING_CONFIRMATION' ? 'When a waiter collects money at a table it appears here until you confirm it.' : 'Try another status or clear the filters.' }}" icon="card" /></td></tr>
                @endforelse
                </tbody></table></div>
            <x-pagination :count="count($rows)" :next="$payments->next()" />
        @endif
    </x-card>

    @if ($canConfirm)
        <x-dialog name="confirm-collection" title="Confirm this collection" subtitle="Check it against the slip, the bank alert or the cash before you confirm.">
            <form method="POST" :action="'{{ url('/finance/collections') }}/' + payload.id + '/confirm'" class="grid gap-4" novalidate>@csrf
                <div class="rounded-lg bg-stone-50 px-3 py-2 text-sm text-stone-700"><b x-text="payload.amount"></b> <span x-text="payload.tenderLabel"></span> for <span x-text="payload.order"></span>.
                    <span x-show="payload.tender === 'CASH'">Count the cash first. It needs an open cash session for you.</span>
                    <span x-show="payload.tender === 'CARD_TERMINAL'">Check the card machine slip: approval code <b x-text="payload.ref"></b>.</span>
                    <span x-show="payload.tender === 'TRANSFER'">Check your bank alert for reference <b x-text="payload.ref"></b> and the amount.</span></div>
                <x-form.text name="matchedReference" label="Reference you matched (optional)" :maxlength="120" hint="For example the alert or slip number you checked." />
                <x-form.text name="note" label="Note (optional)" :maxlength="500" />
                <div class="flex justify-end gap-2"><x-btn type="button" variant="secondary" @click="open = false">Cancel</x-btn><x-btn>Confirm payment</x-btn></div>
            </form>
        </x-dialog>
        <x-dialog name="reject-collection" title="Reject this collection?" subtitle="The bill becomes payable again and a supervisor is alerted.">
            <form method="POST" :action="'{{ url('/finance/collections') }}/' + payload.id + '/reject'" class="grid gap-4" novalidate>@csrf
                <p class="text-sm text-stone-700">Rejecting <b x-text="payload.amount"></b>.</p>
                <x-form.text name="reason" label="Why is it being rejected?" required :multiline="true" :rows="3" :maxlength="500" hint="For example: slip does not match, no bank alert, cash short." />
                <div class="flex justify-end gap-2"><x-btn type="button" variant="secondary" @click="open = false">Cancel</x-btn><x-btn variant="danger">Reject collection</x-btn></div>
            </form>
        </x-dialog>
    @endif
</x-layouts.app>
