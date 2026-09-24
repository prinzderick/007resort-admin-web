@php $facOpts = collect($bookable)->map(fn ($f) => ['value' => $f['id'], 'label' => $f['name'] ?? $f['code']])->values()->all(); @endphp
<x-layouts.app title="Booking resources">
    <x-page-header title="Booking resources" subtitle="Everything that can be booked: courts, salon chairs, rooms, halls. Open one to set its opening windows, blackout dates, rules and what happens when the site is offline." :crumbs="['Setup' => route('setup.index'), 'Booking resources' => null]">
        <x-slot:actions>@if ($canWrite && $facOpts !== [])<x-btn type="button" icon="plus" @click="$dispatch('open-modal', 'add-resource')" data-testid="add-resource">Add resource</x-btn>@endif</x-slot:actions>
    </x-page-header>
    <x-card flush x-data="tableTools">
        <x-table-tools placeholder="Filter resources..." />
        <x-fetch :of="$resources" what="Booking resources" />
        @if ($resources->ok())
            <div class="table-scroll"><table class="data-table" data-testid="resources"><thead><tr><th>Resource</th><th>Facility</th><th>Booked by</th><th class="num">Capacity</th><th class="num">Slot</th><th class="num">Price</th><th>Offline</th><th>Status</th><th class="w-12"></th></tr></thead><tbody>
            @forelse ($resources->items() as $r)
                @php $a = (array) ($r['authority'] ?? []); $strategy = $a['offlineStrategy'] ?? 'A_OFFLINE_ALLOCATION'; @endphp
                <tr data-row class="{{ ($r['active'] ?? true) ? '' : 'opacity-60' }}">
                    <td><a class="font-medium text-brand-700 underline decoration-brand-200 underline-offset-2" href="{{ route('setup.bookings.show', $r['id']) }}">{{ $r['name'] ?? '' }}</a><div class="text-xs text-stone-500">{{ $r['code'] ?? '' }}</div></td>
                    <td>{{ $facilityNames[$r['facilityId'] ?? ''] ?? '' }}</td>
                    <td class="text-sm text-stone-600">{{ $modes[$r['mode'] ?? ''] ?? str_replace('_', ' ', $r['mode'] ?? '') }}</td>
                    <td class="num">{{ $r['capacity'] ?? 1 }}</td>
                    <td class="num">{{ ! empty($r['slotMinutes']) ? $r['slotMinutes'].' min' : '-' }}</td>
                    <td class="num"><x-money :value="$r['price'] ?? '0'" /></td>
                    <td class="text-sm" title="{{ $strategies[$strategy][1] ?? '' }}">{{ $strategies[$strategy][0] ?? $strategy }}@if ($strategy === 'A_OFFLINE_ALLOCATION' && ! empty($a['localReserveUnits']))<div class="text-xs text-stone-500">{{ $a['localReserveUnits'] }} of {{ $r['capacity'] ?? 1 }} kept for the site</div>@endif</td>
                    <td><x-badge :tone="($r['active'] ?? true) ? 'good' : 'default'">{{ ($r['active'] ?? true) ? 'Bookable' : 'Off' }}</x-badge>@if ($r['onlineBookable'] ?? false)<div class="mt-0.5 text-xs text-stone-500">also online</div>@endif</td>
                    <td class="text-right"><a class="text-sm font-medium text-brand-700 underline" href="{{ route('setup.bookings.show', $r['id']) }}">Open</a></td>
                </tr>
            @empty<tr><td colspan="9"><x-empty title="No bookable resources" text="Add a court, chair or hall so it can be booked." icon="calendar" /></td></tr>@endforelse
            </tbody></table></div>
        @endif
    </x-card>
    @if ($canWrite && $facOpts !== [])
        <x-dialog name="add-resource" title="Add a bookable resource" subtitle="You set the opening windows, rules and offline strategy on the next screen.">
            <form method="POST" action="{{ route('setup.bookings.store') }}" class="grid gap-4" novalidate>@csrf
                <x-form.select name="facilityId" label="Facility" required :options="$facOpts" placeholder="Choose a facility" hint="Only facilities with Bookings switched on are listed." />
                <div class="grid gap-4 sm:grid-cols-2"><x-form.text name="name" label="Name" required :maxlength="200" placeholder="e.g. Tennis court 2" /><x-form.text name="code" label="Code" required :maxlength="64" hint="Short and unique, e.g. TENNIS_2." /></div>
                <x-form.radio-cards name="mode" label="How it is booked" :options="collect($modes)->map(fn ($l, $k) => ['value' => $k, 'label' => $l])->values()->all()" value="TIME_SLOT" />
                <div class="grid gap-4 sm:grid-cols-3">
                    <x-form.stepper name="capacity" label="Capacity" :value="1" :min="1" :max="1000" hint="People or units at once." />
                    <x-form.segmented name="slotMinutes" label="Slot length" :options="['30' => '30 min', '60' => '1 hour', '90' => '90 min', '120' => '2 hours']" value="60" />
                    <x-form.money name="price" label="Price per slot" :value="'0'" :scale="2" />
                </div>
                <x-form.toggle name="onlineBookable" label="Bookable on the website" :value="false" />
                <div class="flex justify-end gap-2"><x-btn type="button" variant="secondary" @click="open = false">Cancel</x-btn><x-btn>Add resource</x-btn></div>
            </form>
        </x-dialog>
    @endif
</x-layouts.app>
