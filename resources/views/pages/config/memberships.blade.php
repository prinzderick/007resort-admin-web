@php $canEdit = auth_staff()->can('membership.plan.manage'); @endphp
<x-layouts.app title="Membership plans">
    <x-page-header title="Membership plans" />
    <x-config-nav />
    <x-card title="Plans" flush>
        <x-fetch :of="$plans" what="Plans" />
        @if ($plans->ok())
            <div class="overflow-x-auto"><table class="data-table" data-testid="plans"><thead><tr><th>Plan</th><th>Duration</th><th class="text-right">Price</th><th>Visits</th><th>Scope</th><th>Status</th><th></th></tr></thead><tbody>
            @forelse ($plans->items() as $p)
                <tr><td class="font-medium">{{ $p['name'] ?? '' }}<div class="text-xs font-normal text-stone-500">{{ $p['code'] ?? '' }}@if (! empty($p['memberDiscountPercent']) && $p['memberDiscountPercent'] !== '0.00') &middot; {{ $p['memberDiscountPercent'] }}% member discount @endif</div></td><td>{{ $p['durationDays'] ?? '' }} days</td><td class="text-right"><x-money :value="$p['price'] ?? '0'" /></td><td>{{ $p['visitLimit'] ?? 'Unlimited' }}</td>
                    <td>{{ ($p['propertyWide'] ?? empty($p['facilityIds'])) ? 'Whole property' : count($p['facilityIds'] ?? []).' facility(ies)' }}</td><td><x-badge :tone="($p['active'] ?? true) ? 'good' : 'default'">{{ ($p['active'] ?? true) ? 'Active' : 'Inactive' }}</x-badge></td>
                    <td>@if ($canEdit && ! empty($p['id']))<a class="underline" href="{{ route('config.memberships', ['edit' => $p['id']]) }}#plan-form">Edit</a>@endif</td></tr>
            @empty<tr><td colspan="7" class="text-center text-stone-500">No plans.</td></tr>@endforelse
            </tbody></table></div>
        @endif
    </x-card>
    @if ($canEdit)
        @php $cur = $plans->ok() ? collect($plans->items())->firstWhere('id', $edit) : null; @endphp
        <x-card :title="$cur ? 'Edit plan' : 'New plan'" id="plan-form">
            <form method="POST" action="{{ $cur ? route('config.memberships.update', $cur['id']) : route('config.memberships.store') }}" class="grid gap-x-4 sm:grid-cols-2">
                @csrf @if ($cur) @method('PATCH') @endif
                @unless ($cur)<x-field name="code" label="Code (letters, digits, - _)" required hint="Fixed once created, e.g. GOLD." />@endunless
                <x-field name="name" label="Name" :value="$cur['name'] ?? ''" required /><x-field name="durationDays" label="Duration (days)" type="number" :value="$cur['durationDays'] ?? 30" required />
                <x-field name="price" label="Price (NGN)" :value="$cur['price'] ?? ''" required /><x-field name="visitLimit" label="Visit limit (blank = unlimited)" type="number" :value="$cur['visitLimit'] ?? ''" />
                <x-field name="guestAllowance" label="Guests per visit" type="number" :value="$cur['guestAllowance'] ?? 0" /><x-field name="memberDiscountPercent" label="Member discount (%)" :value="$cur['memberDiscountPercent'] ?? '0'" />
                <x-field name="bookingAdvanceDays" label="Book ahead (days)" type="number" :value="$cur['bookingAdvanceDays'] ?? 0" /><x-field name="gracePeriodDays" label="Grace period after expiry (days)" type="number" :value="$cur['gracePeriodDays'] ?? 0" />
                <x-field name="renewalNoticeDays" label="Renewal notice (days)" type="number" :value="$cur['renewalNoticeDays'] ?? 0" />
                <div class="sm:col-span-2"><x-field name="description" label="Description" :value="$cur['description'] ?? ''" /></div>
                <div class="sm:col-span-2"><div class="mb-1 text-sm font-medium">Valid at (none = whole property)</div><div class="mb-3 flex flex-wrap gap-3">@foreach ($facilities as $f)<label class="flex min-h-11 items-center gap-2 text-sm"><input type="checkbox" name="facilityIds[]" value="{{ $f['id'] ?? '' }}" @checked(in_array($f['id'] ?? '', $cur['facilityIds'] ?? [], true))> {{ $f['name'] ?? '' }}</label>@endforeach</div></div>
                <label class="mb-3 flex min-h-11 items-center gap-2 text-sm sm:col-span-2"><input type="hidden" name="active" value="0"><input type="checkbox" name="active" value="1" @checked($cur['active'] ?? true)> Active (can be sold)</label>
                <div class="sm:col-span-2"><x-btn>{{ $cur ? 'Save plan' : 'Create plan' }}</x-btn> @if ($cur)<x-btn variant="secondary" :href="route('config.memberships')">Cancel</x-btn>@endif</div>
            </form>
        </x-card>
    @endif
</x-layouts.app>
