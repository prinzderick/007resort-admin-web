@php
    $canEdit = auth_staff()->can('membership.plan.manage');
    $facOpts = collect($facilities)->map(fn ($f) => ['value' => $f['id'], 'label' => $f['name'] ?? $f['code']])->values()->all();
    $facName = collect($facilities)->pluck('name', 'id')->all();
    $planRows = $plans->ok() ? $plans->items() : [];
    $dialogs = $canEdit ? array_merge([null], $planRows) : [];
@endphp
<x-layouts.app title="Membership plans">
    <x-page-header title="Membership plans" subtitle="What members buy: how long it lasts, what it costs, which facilities it covers and what perks it includes." :crumbs="['Setup' => route('setup.index'), 'Membership plans' => null]">
        <x-slot:actions>@if ($canEdit)<x-btn type="button" icon="plus" @click="$dispatch('open-modal', 'add-plan')" data-testid="add-plan">Add plan</x-btn>@endif</x-slot:actions>
    </x-page-header>
    <x-card flush x-data="tableTools">
        <x-fetch :of="$plans" what="Plans" />
        @if ($plans->ok())
            <div class="table-scroll"><table class="data-table" data-testid="plans"><thead><tr><th>Plan</th><th>Lasts</th><th class="num">Price</th><th>Visits</th><th class="num">Discount</th><th>Valid at</th><th>Status</th><th class="w-12"></th></tr></thead><tbody>
            @forelse ($planRows as $p)
                <tr data-row>
                    <td><div class="font-medium">{{ $p['name'] ?? '' }}</div><div class="font-mono text-xs text-stone-500">{{ $p['code'] ?? '' }}</div></td>
                    <td>{{ $p['durationDays'] ?? '' }} days</td>
                    <td class="num"><x-money :value="$p['price'] ?? '0'" /></td>
                    <td>{{ $p['visitLimit'] ?? 'Unlimited' }}</td>
                    <td class="num">{{ ! empty($p['memberDiscountPercent']) && (float) $p['memberDiscountPercent'] > 0 ? rtrim(rtrim($p['memberDiscountPercent'], '0'), '.').'%' : '-' }}</td>
                    <td class="text-sm">{{ ($p['propertyWide'] ?? empty($p['facilityIds'])) ? 'Whole property' : collect($p['facilityIds'])->map(fn ($i) => $facName[$i] ?? '')->filter()->take(3)->implode(', ').(count($p['facilityIds']) > 3 ? ' +'.(count($p['facilityIds']) - 3) : '') }}</td>
                    <td><x-badge :tone="($p['active'] ?? true) ? 'good' : 'default'">{{ ($p['active'] ?? true) ? 'On sale' : 'Off' }}</x-badge></td>
                    <td class="text-right">@if ($canEdit && ! empty($p['id']))<button type="button" class="text-sm font-medium text-brand-700 underline" @click="$dispatch('open-modal', 'plan-{{ $p['id'] }}')">Edit</button>@endif</td></tr>
            @empty<tr><td colspan="8"><x-empty title="No plans yet" text="Create a plan such as Gold or Silver." icon="users" /></td></tr>@endforelse
            </tbody></table></div>
        @endif
    </x-card>
    @foreach ($dialogs as $cur)
        @php $isNew = $cur === null; @endphp
        <x-dialog :name="$isNew ? 'add-plan' : 'plan-'.$cur['id']" :title="$isNew ? 'Add a membership plan' : 'Edit '.$cur['name']" maxWidth="max-w-3xl" :subtitle="$isNew ? null : 'The code cannot be changed. Members who already bought keep the terms they bought.'">
            <form method="POST" action="{{ $isNew ? route('setup.memberships.store') : route('setup.memberships.update', $cur['id']) }}" class="grid gap-4" x-data="{ scope: '{{ ($cur['propertyWide'] ?? true) ? 'ALL' : 'SOME' }}' }" novalidate>
                @csrf @if (! $isNew) @method('PATCH') @endif
                <div class="grid gap-4 sm:grid-cols-2">
                    <x-form.text name="name" label="Name" required :value="$cur['name'] ?? ''" :maxlength="120" />
                    @if ($isNew)<x-form.text name="code" label="Code" required :maxlength="32" hint="Letters, numbers, - and _. E.g. GOLD. Fixed once created." />@endif
                </div>
                <x-form.text name="description" label="Description" :value="$cur['description'] ?? ''" :multiline="true" :rows="2" :maxlength="500" />
                <div class="grid gap-4 sm:grid-cols-2">
                    <x-form.stepper name="durationDays" label="Lasts (days)" :value="(int) ($cur['durationDays'] ?? 30)" :min="1" :max="3650" :step="1" unit="days" />
                    <x-form.money name="price" label="Price" required :value="$cur['price'] ?? null" :scale="2" :quick="['10000', '50000', '150000']" />
                </div>
                <x-form.percent name="memberDiscountPercent" label="Member discount" :value="(float) ($cur['memberDiscountPercent'] ?? 0)" :step="0.5" hint="Taken off purchases at the facilities where the plan is valid." />
                <div class="grid gap-4 sm:grid-cols-2">
                    <x-form.stepper name="visitLimit" label="Visit limit" :value="$cur['visitLimit'] ?? null" :min="1" :max="100000" :nullable="true" placeholder="Unlimited" hint="Empty means unlimited visits." />
                    <x-form.stepper name="guestAllowance" label="Guests per visit" :value="(int) ($cur['guestAllowance'] ?? 0)" :min="0" :max="50" />
                    <x-form.slider name="bookingAdvanceDays" label="Book ahead" :value="(int) ($cur['bookingAdvanceDays'] ?? 0)" :min="0" :max="60" :step="1" unit=" days" :ticks="5" hint="How many days earlier than others a member may book." />
                    <x-form.slider name="gracePeriodDays" label="Grace after expiry" :value="(int) ($cur['gracePeriodDays'] ?? 0)" :min="0" :max="30" :step="1" unit=" days" :ticks="5" />
                    <x-form.slider name="renewalNoticeDays" label="Renewal reminder" :value="(int) ($cur['renewalNoticeDays'] ?? 0)" :min="0" :max="30" :step="1" unit=" days" :ticks="5" hint="Remind the member this long before it expires." />
                </div>
                <x-form.segmented name="_scope" label="Valid at" :options="['ALL' => 'Whole property', 'SOME' => 'Chosen facilities']" x-model="scope" :value="($cur['propertyWide'] ?? true) ? 'ALL' : 'SOME'" />
                <div x-show="scope === 'SOME'" x-cloak><x-form.checkbox-group name="facilityIds" label="Facilities" :options="$facOpts" :value="$cur['facilityIds'] ?? []" :select-all="true" :cols="2" /></div>
                <x-form.toggle name="active" label="On sale" description="Off stops new sales. Existing members are not affected." :value="$cur['active'] ?? true" />
                <div class="flex justify-end gap-2"><x-btn type="button" variant="secondary" @click="open = false">Cancel</x-btn><x-btn>{{ $isNew ? 'Create plan' : 'Save plan' }}</x-btn></div>
            </form>
        </x-dialog>
    @endforeach
</x-layouts.app>
