@php
    $statuses = ['PENDING_RECEIPT' => 'Waiting to be counted', 'PENDING_SIGNOFF' => 'Needs supervisor sign-off', 'RECEIVED' => 'Received'];
    $name = fn ($id) => $staffNames[$id] ?? 'Waiter '.\App\Services\Portal\Directory::short($id);
@endphp
<x-layouts.app title="Cash handovers">
    <x-page-header title="Cash handovers" subtitle="Waiters hand the cash they collected to a cashier. The cashier counts it here; a difference above the allowed variance needs a supervisor to sign it off." :crumbs="['Finance' => route('finance.payments'), 'Cash handovers' => null]">
        <x-slot:actions><x-btn variant="secondary" :href="route('finance.collections')" icon="card">Collected by waiters</x-btn></x-slot:actions>
    </x-page-header>

    <x-card title="Cash in hand" subtitle="What each waiter is holding right now and whether they must hand over." flush data-testid="cash-in-hand">
        @if ($inHand === [])
            <x-empty title="Nobody is holding cash" text="Waiters who collect cash at tables appear here." icon="cash" />
        @else
            <div class="table-scroll"><table class="data-table"><thead><tr><th>Waiter</th><th class="num">Cash in hand</th><th class="num">Limit</th><th class="num">Waiting for confirmation</th><th class="num">Short, not signed off</th><th>Status</th></tr></thead><tbody>
            @foreach ($inHand as $id => $h)
                @php $over = ! empty($h['handoverRequired']); @endphp
                <tr>
                    <td class="font-medium">{{ $name($id) }}@if (empty($h['cashHoldingAllowed']))<div class="text-xs font-normal text-stone-500">Not allowed to hold cash</div>@endif</td>
                    <td class="num"><x-money :value="$h['cashInHand'] ?? '0'" /></td>
                    <td class="num">@if (! empty($h['limit']) && \App\Support\Money::cmp($h['limit'], '0') > 0)<x-money :value="$h['limit']" />@else<span class="text-stone-400">No limit</span>@endif</td>
                    <td class="num">{{ (int) ($h['pendingCollections'] ?? 0) }} &middot; <x-money :value="$h['pendingCollectionsAmount'] ?? '0'" /></td>
                    <td class="num">@if (\App\Support\Money::cmp($h['unsignedShortfall'] ?? '0', '0') > 0)<x-money :value="$h['unsignedShortfall']" class="text-red-700" />@else<span class="text-stone-400">-</span>@endif</td>
                    <td>@if ($over)<x-badge tone="bad">Must hand over</x-badge>@elseif (\App\Support\Money::cmp($h['cashInHand'] ?? '0', '0') > 0)<x-badge tone="warn">Holding cash</x-badge>@else<x-badge tone="good">Clear</x-badge>@endif</td>
                </tr>
            @endforeach
            </tbody></table></div>
        @endif
    </x-card>

    <x-filter-form :reset="route('finance.handovers')">
        <x-filter-select name="status" label="Status" :options="$statuses" :value="$status" all="All handovers" />
        <x-filter-select name="facilityId" label="Facility" :options="collect($facilities)->pluck('name', 'id')->all()" :value="request('facilityId')" all="All facilities" />
    </x-filter-form>

    <x-card flush x-data="tableTools" title="Handovers">
        <x-fetch :of="$handovers" what="Cash handovers" />
        @if ($handovers->ok())
            <div class="table-scroll"><table class="data-table" data-testid="handovers-table">
                <thead><tr><th>When</th><th>Waiter</th><th>Facility</th><th class="num">Declared</th><th class="num">Counted</th><th class="num">Difference</th><th>Status</th><th class="w-44"></th></tr></thead>
                <tbody>
                @forelse ($handovers->items() as $h)
                    @php
                        $st = $h['status'] ?? '';
                        $var = $h['variance'] ?? null;
                        $neg = $var !== null && \App\Support\Money::cmp($var, '0') < 0;
                    @endphp
                    <tr data-row>
                        <td class="whitespace-nowrap"><x-time :at="$h['createdAt'] ?? null" /></td>
                        <td class="font-medium">{{ $name($h['waiterStaffId'] ?? null) }}@if (! empty($h['note']))<div class="max-w-48 truncate text-xs font-normal text-stone-500" title="{{ $h['note'] }}">{{ $h['note'] }}</div>@endif</td>
                        <td>{{ $facilityNames[$h['facilityId'] ?? ''] ?? '-' }}</td>
                        <td class="num"><x-money :value="$h['declaredAmount'] ?? '0'" /></td>
                        <td class="num">@if (isset($h['countedAmount']))<x-money :value="$h['countedAmount']" />@else<span class="text-stone-400">-</span>@endif</td>
                        <td class="num">@if ($var !== null && \App\Support\Money::cmp($var, '0') !== 0)<x-money :value="$var" /> <span class="text-xs {{ $neg ? 'text-red-700' : 'text-amber-700' }}">{{ $neg ? 'short' : 'over' }}</span>@elseif ($var !== null)<span class="text-brand-700">Balanced</span>@else<span class="text-stone-400">-</span>@endif</td>
                        <td><x-badge :status="$st" tone="{{ $st === 'PENDING_SIGNOFF' ? 'warn' : ($st === 'RECEIVED' ? 'good' : 'info') }}">{{ $statuses[$st] ?? $st }}</x-badge></td>
                        <td class="text-right">
                            @if ($st === 'PENDING_RECEIPT' && $canReceive)<x-btn type="button" @click="$dispatch('open-modal', { name: 'receive-handover', data: {{ \Illuminate\Support\Js::from(['id' => $h['id'], 'waiter' => $name($h['waiterStaffId'] ?? null), 'declared' => \App\Support\Money::format($h['declaredAmount'] ?? '0'), 'declaredRaw' => $h['declaredAmount'] ?? '']) }} })">Count &amp; receive</x-btn>
                            @elseif ($st === 'PENDING_SIGNOFF' && $canSignoff)<x-btn type="button" variant="secondary" @click="$dispatch('open-modal', { name: 'signoff-handover', data: {{ \Illuminate\Support\Js::from(['id' => $h['id'], 'text' => 'Counted '.\App\Support\Money::format($h['countedAmount'] ?? '0').' against '.\App\Support\Money::format($h['declaredAmount'] ?? '0').' declared: '.\App\Support\Money::format($h['variance'] ?? '0').'.']) }} })">Sign off</x-btn>@endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="8"><x-empty title="No handovers" text="A handover is created when a waiter hands cash to the cashier." icon="cash" /></td></tr>
                @endforelse
                </tbody></table></div>
        @endif
    </x-card>

    @if ($canReceive)
        <x-dialog name="receive-handover" title="Receive cash">
            <form method="POST" :action="'{{ url('/finance/handovers') }}/' + payload.id + '/receive'" class="grid gap-4" novalidate>@csrf
                <p class="text-sm text-stone-700"><b x-text="payload.waiter"></b> declared <b x-text="payload.declared"></b>. Count it and enter what you actually received.</p>
                <x-form.money name="countedAmount" label="Cash counted" required :scale="2" x-model="payload.declaredRaw" />
                <x-form.text name="note" label="Note (optional)" :maxlength="500" />
                <div class="flex justify-end gap-2"><x-btn type="button" variant="secondary" @click="open = false">Cancel</x-btn><x-btn>Receive cash</x-btn></div>
            </form>
        </x-dialog>
    @endif
    @if ($canSignoff)
        <x-dialog name="signoff-handover" title="Sign off the difference">
            <form method="POST" :action="'{{ url('/finance/handovers') }}/' + payload.id + '/signoff'" class="grid gap-4" novalidate>@csrf
                <p class="text-sm text-stone-700" x-text="payload.text"></p>
                <x-form.text name="note" label="What happened?" required :multiline="true" :rows="3" :maxlength="500" hint="This is kept in the audit log with your name." />
                <div class="flex justify-end gap-2"><x-btn type="button" variant="secondary" @click="open = false">Cancel</x-btn><x-btn>Sign off</x-btn></div>
            </form>
        </x-dialog>
    @endif
</x-layouts.app>
