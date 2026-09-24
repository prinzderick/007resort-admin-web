@php
    $facName = collect($facilities)->pluck('name', 'id')->all();
    $facOpts = collect($ticketFacilities)->map(fn ($f) => ['value' => $f['id'], 'label' => $f['name'] ?? $f['code']])->values()->all();
    $modeCards = collect($modes)->map(fn ($m, $k) => ['value' => $k, 'label' => $m[0], 'description' => $m[1], 'icon' => $m[2]])->values()->all();
@endphp
<x-layouts.app title="Ticket types">
    <x-page-header title="Ticket types" subtitle="What can be sold as a pass or ticket, what it costs, and how it is scanned at the gate." :crumbs="['Setup' => route('setup.index'), 'Ticket types' => null]">
        <x-slot:actions>@if ($canManage && $tab === 'types')<x-btn type="button" icon="plus" @click="$dispatch('open-modal', 'add-ticket-type')" data-testid="add-ticket-type">Add ticket type</x-btn>@endif</x-slot:actions>
    </x-page-header>
    <x-tabs :tabs="['types' => 'Ticket types', 'issued' => 'Issued tickets']" :current="$tab" />

    @if ($tab === 'types')
        <x-card flush>
            <x-fetch :of="$types" what="Ticket types" />
            @if ($types->ok())
                <div class="table-scroll"><table class="data-table" data-testid="ticket-types"><thead><tr><th>Ticket</th><th>Facility</th><th>Scanning</th><th>Valid</th><th class="num">Price</th><th>Status</th><th class="w-12"></th></tr></thead><tbody>
                @forelse ($types->items() as $t)
                    <tr><td><div class="font-medium">{{ $t['name'] }}</div><div class="font-mono text-xs text-stone-500">{{ $t['code'] }}</div></td>
                        <td>{{ $facName[$t['facilityId'] ?? ''] ?? '-' }}</td>
                        <td>{{ $modes[$t['validationMode'] ?? '']['0'] ?? $t['validationMode'] }}</td>
                        <td class="text-sm text-stone-600">{{ $validityKinds[$t['validityKind'] ?? ''] ?? '' }}@if (! empty($t['validityMinutes']))<div class="text-xs">{{ $t['validityMinutes'] }} min</div>@endif</td>
                        <td class="num">@if (isset($t['price']))<x-money :value="$t['price']" />@else<span class="text-stone-400">-</span>@endif</td>
                        <td><x-badge :tone="($t['active'] ?? true) ? 'good' : 'default'">{{ ($t['active'] ?? true) ? 'On sale' : 'Off' }}</x-badge></td>
                        <td class="text-right">@if ($canManage)<button type="button" class="text-sm font-medium text-brand-700 underline" @click="$dispatch('open-modal', 'ticket-{{ $t['id'] }}')">Edit</button>@endif</td></tr>
                @empty<tr><td colspan="7"><x-empty title="No ticket types yet" text="Add one, for example Pool - Adult, then it can be sold at reception." icon="ticket" /></td></tr>@endforelse
                </tbody></table></div>
            @endif
        </x-card>
        @if ($canManage)
            @foreach (array_merge([null], $types->items()) as $t)
                @php $isNew = $t === null; @endphp
                <x-dialog :name="$isNew ? 'add-ticket-type' : 'ticket-'.$t['id']" :title="$isNew ? 'Add a ticket type' : 'Edit '.$t['name']" maxWidth="max-w-2xl" :subtitle="$isNew ? 'Something you can sell as a pass or ticket.' : 'Code and facility cannot be changed.'">
                    <form method="POST" action="{{ $isNew ? route('setup.tickets.store') : route('setup.tickets.update', $t['id']) }}" class="grid gap-4" x-data="{ vk: '{{ $t['validityKind'] ?? 'ISSUE_DAY' }}' }" novalidate>@csrf @if (! $isNew) @method('PATCH') <input type="hidden" name="etag" value="{{ $t['rowVersion'] ?? '' }}"> @endif
                        <div class="grid gap-4 sm:grid-cols-2">
                            <x-form.text name="name" label="Name" required :value="$t['name'] ?? ''" :maxlength="120" placeholder="e.g. Pool - Adult" />
                            @if ($isNew)<x-form.text name="code" label="Code" required :maxlength="40" hint="Short and unique, e.g. POOL-ADULT." />@endif
                        </div>
                        @if ($isNew)<x-form.select name="facilityId" label="Sold and scanned at" required :options="$facOpts" placeholder="Choose a facility" hint="Only facilities with ticket sales or ticket validation switched on are listed." />@endif
                        <x-form.radio-cards name="validationMode" label="How it is scanned" :options="$modeCards" :value="$t['validationMode'] ?? 'SINGLE_USE'" />
                        <div class="grid gap-4 sm:grid-cols-2">
                            <x-form.segmented name="format" label="Format" :options="['INDIVIDUAL' => 'One per person', 'COMBINED' => 'Combined (group)']" :value="$t['format'] ?? 'INDIVIDUAL'" />
                            <x-form.money name="price" label="Price" :value="$t['price'] ?? null" :scale="2" :quick="['1000', '3000', '5000']" />
                        </div>
                        <x-form.radio-cards name="validityKind" label="How long it stays valid" x-model="vk" :options="collect($validityKinds)->map(fn ($l, $k) => ['value' => $k, 'label' => $l])->values()->all()" :value="$t['validityKind'] ?? 'ISSUE_DAY'" />
                        <div x-show="vk === 'DURATION_MINUTES'" x-cloak><x-form.duration name="validityMinutes" label="Valid for" unit="minutes" :units="['minutes', 'hours', 'days']" :value="$t['validityMinutes'] ?? 120" :min="1" :max="525600" /></div>
                        <x-form.slider name="earlyEntryMinutes" label="Early entry" :value="(int) ($t['earlyEntryMinutes'] ?? 0)" :min="0" :max="60" :step="5" unit=" min" :ticks="5" hint="How long before the slot starts a booked ticket already works." />
                        <x-form.toggle name="active" label="On sale" description="Off stops new sales. Tickets already issued still work." :value="$t['active'] ?? true" />
                        <div class="flex justify-end gap-2"><x-btn type="button" variant="secondary" @click="open = false">Cancel</x-btn><x-btn>{{ $isNew ? 'Add ticket type' : 'Save' }}</x-btn></div>
                    </form>
                </x-dialog>
            @endforeach
        @endif
    @else
        <x-card title="Issued tickets and passes" subtitle="Read-only. Everything that has been sold and how much of it has been used." flush>
            <x-fetch :of="$entitlements" what="Issued tickets" />
            @if ($entitlements->ok())
                <div class="table-scroll"><table class="data-table" data-testid="entitlements"><thead><tr><th>Issued</th><th>Holder</th><th>Items</th><th>Status</th></tr></thead><tbody>
                @forelse ($entitlements->items() as $e)
                    <tr><td class="whitespace-nowrap"><x-time :at="$e['issuedAt'] ?? null" /></td><td>{{ $e['holderName'] ?? '' }}</td>
                        <td class="text-xs">@foreach ($e['items'] ?? [] as $i){{ $i['name'] ?? '' }} <span class="text-stone-500">({{ str_replace('_', ' ', strtolower($i['validationMode'] ?? '')) }}, {{ $i['quantityRedeemed'] ?? 0 }}/{{ $i['quantity'] ?? 0 }} used)</span><br>@endforeach</td>
                        <td><x-badge :status="$e['status'] ?? 'UNKNOWN'" /></td></tr>
                @empty<tr><td colspan="4"><x-empty title="Nothing issued yet" icon="ticket" /></td></tr>@endforelse
                </tbody></table></div>
                <x-pagination :count="count($entitlements->items())" :next="$entitlements->next()" />
            @endif
        </x-card>
    @endif
</x-layouts.app>
