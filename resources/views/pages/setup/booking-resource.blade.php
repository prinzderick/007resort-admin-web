@php
    $a = (array) ($r['authority'] ?? []);
    $cap = (int) ($r['capacity'] ?? 1);
    $pct = $cap > 0 ? (int) round(((int) ($a['localReserveUnits'] ?? 0)) / $cap * 100) : 0;
    $cards = collect($strategies)->map(fn ($s, $k) => ['value' => $k, 'label' => $s[0], 'description' => $s[1], 'icon' => $s[2]])->values()->all();
    $ovr = collect($overrides);
@endphp
<x-layouts.app :title="$r['name'] ?? 'Booking resource'">
    <x-page-header :title="$r['name'] ?? 'Booking resource'" :subtitle="($facilityName ? $facilityName.' · ' : '').($r['code'] ?? '')" :crumbs="['Setup' => route('setup.index'), 'Booking resources' => route('setup.bookings'), ($r['name'] ?? 'Resource') => null]" />
    <x-fetch :of="$fetch" what="Booking resource" />
    @if ($fetch->ok())
    <x-settings-meta entity-type="BookableResource" :entity-id="$id" />

    <form method="POST" action="{{ route('setup.bookings.update', $id) }}" novalidate data-testid="resource-form">@csrf @method('PATCH')
        <x-form.section title="Basics" description="What it is, how big it is and what it costs.">
            <x-form.text name="name" label="Name" required :value="old('name', $r['name'] ?? '')" :disabled="! $canWrite" />
            <div class="grid gap-4 sm:grid-cols-3">
                <x-form.stepper name="capacity" label="Capacity" :value="(int) ($r['capacity'] ?? 1)" :min="1" :max="1000" :disabled="! $canWrite" />
                <x-form.segmented name="slotMinutes" label="Slot length" :options="['30' => '30 min', '60' => '1 hour', '90' => '90 min', '120' => '2 hours']" :value="(string) ($r['slotMinutes'] ?? 60)" :disabled="! $canWrite" />
                <x-form.stepper name="maxSlotsPerBooking" label="Slots per booking" :value="(int) ($r['maxSlotsPerBooking'] ?? 1)" :min="1" :max="48" hint="How many slots one booking may take." :disabled="! $canWrite" />
            </div>
            <x-form.money name="price" label="Price per slot" :value="$r['price'] ?? '0'" :scale="2" :quick="['1000', '3000', '5000', '8000']" :disabled="! $canWrite" />
            <x-form.toggle name="onlineBookable" label="Bookable on the website" description="Customers can reserve it online." :value="$r['onlineBookable'] ?? false" :disabled="! $canWrite" />
            <x-form.toggle name="allowWholeResource" label="Allow booking the whole thing" description="Let someone take all the capacity at once." :value="$r['allowWholeResource'] ?? false" :disabled="! $canWrite" />
            <x-form.toggle name="active" label="Bookable" description="Off hides it from booking. Existing bookings stay." :value="$r['active'] ?? true" :disabled="! $canWrite" />
        </x-form.section>

        <div x-data="{ strategy: '{{ $a['offlineStrategy'] ?? 'A_OFFLINE_ALLOCATION' }}', cap: {{ $cap }}, pct: {{ $pct }} }">
            <x-form.section title="When the site is offline" description="What happens to bookings for this resource if the on-site server loses its link to the cloud." id="offline">
                <x-form.radio-cards name="offlineStrategy" label="Strategy" x-model="strategy" :options="$cards" :value="$a['offlineStrategy'] ?? 'A_OFFLINE_ALLOCATION'" :disabled="! $canWrite" />
                <div x-show="strategy === 'A_OFFLINE_ALLOCATION'" x-cloak>
                    <x-form.percent name="reservePercent" label="Share kept for the site while offline" :value="$pct" :step="5" x-model="pct" hint="The rest is only bookable online. Rounded down to whole units." :disabled="! $canWrite" />
                    <p class="f-hint">That is <b x-text="Math.floor(cap * pct / 100)"></b> of <span x-text="cap"></span> unit(s) reserved for Reception.</p>
                </div>
                <x-form.duration name="onlineStaleAfterSeconds" label="Treat the site as offline after" unit="seconds" :units="['minutes', 'hours']" :value="(int) ($a['onlineStaleAfterSeconds'] ?? 900)" :min="30" :max="604800" :slider="true" hint="How long without a signal from the cloud before the strategy above takes over." :disabled="! $canWrite" />
            </x-form.section>
        </div>
        @if ($canWrite)<x-form.actions submit="Save resource" />@endif
    </form>

    <form method="POST" action="{{ route('setup.bookings.schedule', $id) }}" class="mt-8" novalidate data-testid="schedule-form">@csrf @method('PUT')
        <x-form.section title="Opening windows" description="When this resource can be booked each day. Leave every day closed to follow the property's default hours." id="schedule" stacked>
            <x-form.weekly-hours name="hours" label="Weekly windows" :value="$week" :exceptions="false" :disabled="! $canWrite" />
        </x-form.section>
        @if ($canWrite)<x-form.actions submit="Save opening windows" />@endif
    </form>

    <x-card title="Blackout dates" subtitle="Periods when nothing can be booked: maintenance, tournaments, holidays." flush class="mt-8" id="blackouts" data-testid="blackouts">
        <x-slot:aside>@if ($canWrite)<x-btn type="button" variant="secondary" icon="plus" @click="$dispatch('open-modal', 'add-blackout')">Add blackout</x-btn>@endif</x-slot:aside>
        <x-fetch :of="$blackouts" what="Blackout dates" />
        @if ($blackouts->ok())
            <div class="table-scroll"><table class="data-table"><thead><tr><th>From</th><th>To</th><th>Reason</th><th class="w-12"></th></tr></thead><tbody>
            @forelse ($blackouts->items() as $b)
                <tr><td><x-time :at="$b['start'] ?? null" /></td><td><x-time :at="$b['end'] ?? null" /></td><td>{{ $b['reason'] ?? '-' }}</td>
                    <td class="text-right">@if ($canWrite)<form method="POST" action="{{ route('setup.bookings.blackouts.destroy', [$id, $b['id']]) }}" x-data="confirmSubmit('Remove this blackout?')" @submit="ask($event)">@csrf @method('DELETE')<button class="text-sm text-red-800 underline">Remove</button></form>@endif</td></tr>
            @empty<tr><td colspan="4"><x-empty title="No blackout dates" text="Add a period to block bookings." icon="calendar" /></td></tr>@endforelse
            </tbody></table></div>
        @endif
    </x-card>
    @if ($canWrite)
        <x-dialog name="add-blackout" title="Add a blackout" subtitle="Whole days, property time. Existing bookings in the period are not cancelled.">
            <form method="POST" action="{{ route('setup.bookings.blackouts.store', $id) }}" class="grid gap-4" novalidate>@csrf
                <div class="grid gap-4 sm:grid-cols-2"><x-form.date name="from" label="First day" required /><x-form.date name="to" label="Last day" required /></div>
                <x-form.text name="reason" label="Reason" :maxlength="255" placeholder="e.g. Court resurfacing" />
                <div class="flex justify-end gap-2"><x-btn type="button" variant="secondary" @click="open = false">Cancel</x-btn><x-btn>Add blackout</x-btn></div>
            </form>
        </x-dialog>
    @endif

    @if ($rules->ok() && $ruleDefs !== [])
        <form method="POST" action="{{ route('setup.bookings.rules', $id) }}" class="mt-8" novalidate data-testid="rules-form" id="rules">@csrf @method('PUT')
            <x-form.section title="Rules for this resource" description="These start out following the facility's booking rules. Change a value to give this resource its own rule; the others keep following the facility." stacked>
                @if ($ovr->isNotEmpty())<p class="rounded-lg border border-sky-200 bg-sky-50 px-3 py-2 text-sm text-sky-900" data-testid="overrides">This resource has its own value for: {{ collect($ruleDefs)->whereIn('key', $ovr->map(fn ($c) => array_search($c, $overrideKeys))->all())->pluck('label')->implode(', ') }}.</p>@endif
                <x-form.schema :definitions="$ruleDefs" :values="$ruleValues" name="rules" :sections="false" />
            </x-form.section>
            @if ($canWrite)
                <x-form.actions submit="Save rules">
                    @if ($ovr->isNotEmpty())<button type="submit" name="clear" value="1" class="f-btn" data-variant="secondary">Use the facility rules again</button>@endif
                </x-form.actions>
            @endif
        </form>
    @endif
    @endif
</x-layouts.app>
