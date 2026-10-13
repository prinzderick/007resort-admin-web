@php
    $fetched = $board;
    $groups = $board?->ok() ? ($board->data['groups'] ?? []) : [];
    $totals = $board?->ok() ? ($board->data['totals'] ?? []) : [];
    $waiterList = $board?->ok() ? ($board->data['waiters'] ?? []) : [];
    $canManage = true;
@endphp
<x-layouts.app title="Open tabs">
    <x-page-header title="Open tabs" subtitle="Every unpaid order and tab, and which waiter is looking after it. Waiters take payment on their own tablets; you can take payment on their behalf or move a tab to another waiter here.">
        <x-slot:actions><x-btn variant="secondary" :href="route('waiter-flow.handovers', ['facilityId' => $facilityId])" icon="repeat">Hand-overs</x-btn></x-slot:actions>
    </x-page-header>

    <x-filter-form :reset="route('waiter-flow.open-tabs')">
        <x-filter-select name="facilityId" label="Facility" :options="collect($facilities)->pluck('name', 'id')->all()" :value="$facilityId" all="" />
        <x-filter-select name="staffId" label="Waiter" :options="$waiters" :value="$staffId" all="All waiters" />
    </x-filter-form>

    @if ($board === null)
        <x-empty title="No table-service facility" text="Turn on the waiter-only service flow for a facility under Facilities > Rules." icon="cart" />
    @else
        <x-fetch :of="$board" what="Open tabs" />
        @if ($board->ok())
            <div class="mb-4 grid gap-3 sm:grid-cols-3">
                <x-stat-card label="Open tabs" :value="(string) ($totals['tabs'] ?? 0)" />
                <x-stat-card label="Orders" :value="(string) ($totals['orders'] ?? 0)" />
                <x-stat-card label="Owed" :value="\App\Support\Money::format($totals['balance'] ?? '0')" />
            </div>

            @if ($waiterList !== [])
                <x-card title="By waiter" flush class="mb-4">
                    <div class="table-scroll"><table class="data-table"><thead><tr><th>Waiter</th><th class="num">Tabs</th><th class="num">Orders</th><th class="num">Owed</th></tr></thead><tbody>
                    @foreach ($waiterList as $w)
                        <tr><td class="font-medium"><a class="text-brand-700 hover:underline" href="{{ route('waiter-flow.open-tabs', ['facilityId' => $facilityId, 'staffId' => $w['staffId']]) }}">{{ $w['name'] ?? $w['staffId'] }}</a></td><td class="num">{{ $w['tabs'] }}</td><td class="num">{{ $w['orders'] }}</td><td class="num"><x-money :value="$w['balance']" /></td></tr>
                    @endforeach
                    </tbody></table></div>
                </x-card>
            @endif

            <x-card flush title="Unpaid">
                @if ($groups === [])
                    <x-empty title="Nothing is owed" text="Every order at this facility is paid." icon="cart" />
                @else
                    <div class="table-scroll"><table class="data-table" data-testid="open-tabs-table">
                        <thead><tr><th>Table / customer</th><th>Waiter</th><th>Orders</th><th>Since</th><th class="num">Owed</th><th>Status</th><th class="w-56"></th></tr></thead>
                        <tbody>
                        @foreach ($groups as $g)
                            @php
                                $ids = collect($g['orders'])->pluck('id')->implode(',');
                                $label = trim(($g['tableLabel'] ?? '').' '.($g['customerName'] ?? '')) ?: 'Order '.($g['orders'][0]['number'] ?? '');
                            @endphp
                            <tr>
                                <td class="font-medium">{{ $label }}
                                    <div class="text-xs font-normal text-stone-500">@foreach ($g['orders'] as $o){{ $o['number'] }}@if (! $loop->last), @endif @endforeach</div></td>
                                <td>{{ $g['ownerName'] ?? '-' }}</td>
                                <td class="text-xs text-stone-600">@foreach ($g['orders'] as $o)<div>{{ collect($o['lines'])->map(fn ($l) => $l['quantity'].' x '.$l['name'])->implode(', ') }}</div>@endforeach</td>
                                <td class="whitespace-nowrap"><x-time :at="$g['oldestAt'] ?? null" ago /></td>
                                <td class="num"><x-money :value="$g['balance']" /></td>
                                <td>@if ($g['handoverPending'])<x-badge tone="warn">Hand-over pending</x-badge>@elseif ($g['allServed'])<x-badge tone="info">Served, unpaid</x-badge>@else<x-badge tone="neutral">Not served yet</x-badge>@endif</td>
                                <td class="text-right whitespace-nowrap">
                                    @if ($g['allServed'])
                                    <x-btn type="button" @click="$dispatch('open-modal', { name: 'pay-tab', data: {{ \Illuminate\Support\Js::from(['ids' => $ids, 'label' => $label, 'waiter' => $g['ownerName'] ?? '', 'amount' => $g['balance'], 'shown' => \App\Support\Money::format($g['balance'])]) }} })">Take payment</x-btn>
                                    @endif
                                    <x-btn type="button" variant="secondary" @click="$dispatch('open-modal', { name: 'reassign-tab', data: {{ \Illuminate\Support\Js::from(['ids' => $ids, 'label' => $label, 'waiter' => $g['ownerName'] ?? '']) }} })">Reassign</x-btn>
                                </td>
                            </tr>
                        @endforeach
                        </tbody></table></div>
                @endif
            </x-card>

            <x-dialog name="pay-tab" title="Take payment on the waiter's behalf">
                <form method="POST" action="{{ route('waiter-flow.pay') }}" class="grid gap-4" novalidate>@csrf
                    <input type="hidden" name="facilityId" value="{{ $facilityId }}">
                    <input type="hidden" name="orderIds" :value="payload.ids">
                    <p class="text-sm text-stone-700"><b x-text="payload.label"></b> (waiter <span x-text="payload.waiter"></span>) owes <b x-text="payload.shown"></b>. The payment is recorded against you and the waiter's name.</p>
                    <label class="block text-sm font-medium text-stone-700">How was it paid?
                        <select name="tenderType" class="mt-1 min-h-10 w-full rounded-lg border border-stone-300 bg-white px-2 text-sm">@foreach ($tenders as $v => $l)<option value="{{ $v }}">{{ $l }}</option>@endforeach</select></label>
                    <x-form.money name="amount" label="Amount received" required :scale="2" x-model="payload.amount" />
                    <x-form.text name="reference" label="Slip or transfer reference (for card and transfer)" :maxlength="128" />
                    <div class="flex justify-end gap-2"><x-btn type="button" variant="secondary" @click="open = false">Cancel</x-btn><x-btn>Record payment</x-btn></div>
                </form>
            </x-dialog>

            <x-dialog name="reassign-tab" title="Move to another waiter">
                <form method="POST" action="{{ route('waiter-flow.reassign') }}" class="grid gap-4" novalidate>@csrf
                    <input type="hidden" name="facilityId" value="{{ $facilityId }}">
                    <input type="hidden" name="orderIds" :value="payload.ids">
                    <p class="text-sm text-stone-700"><b x-text="payload.label"></b> is with <span x-text="payload.waiter"></span>. It moves at once and is recorded in the audit log with your reason.</p>
                    <label class="block text-sm font-medium text-stone-700">New waiter
                        <select name="toStaffId" required class="mt-1 min-h-10 w-full rounded-lg border border-stone-300 bg-white px-2 text-sm">
                            @foreach ($staffNames as $id => $n)<option value="{{ $id }}">{{ $n }}</option>@endforeach
                        </select></label>
                    <x-form.text name="reason" label="Reason" required :maxlength="255" />
                    <div class="flex justify-end gap-2"><x-btn type="button" variant="secondary" @click="open = false">Cancel</x-btn><x-btn>Reassign</x-btn></div>
                </form>
            </x-dialog>
        @endif
    @endif
</x-layouts.app>
