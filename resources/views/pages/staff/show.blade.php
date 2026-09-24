@php
    $displayName = $member['displayName'] ?? trim(($member['firstName'] ?? '').' '.($member['lastName'] ?? '')) ?: 'Staff member';
    $ovr = $policy->ok() ? ($policy->data['staffOverride'] ?? []) : [];
    $eff = $policy->ok() ? $policy->data : [];
    $tenderNames = ['CASH' => 'Cash', 'CARD_TERMINAL' => 'Card machine', 'TRANSFER' => 'Bank transfer', 'PAY_LINK' => 'Pay link'];
@endphp
<x-layouts.app :title="$displayName">
    <x-page-header :title="$displayName" :subtitle="'Staff no. '.($member['staffNumber'] ?? '-')" :crumbs="['People' => route('staff.index'), $displayName => null]">
        <x-slot:actions><x-badge :status="$member['status'] ?? 'ACTIVE'" /><x-btn variant="secondary" :href="route('staff.index')">All staff</x-btn></x-slot:actions>
    </x-page-header>

    <form method="POST" action="{{ route('staff.update', $member['id'] ?? '') }}" novalidate data-testid="profile-form">
        @csrf @method('PATCH')
        <input type="hidden" name="etag" value="{{ $etag }}">
        <x-form.section title="Profile" description="Name and contact details. Suspending or ending someone stops them signing in straight away.">
            <div class="grid gap-4 sm:grid-cols-2">
                <x-form.text name="firstName" label="First name" :value="old('firstName', $member['firstName'] ?? '')" required />
                <x-form.text name="lastName" label="Last name" :value="old('lastName', $member['lastName'] ?? '')" required />
                <x-form.text name="email" label="Email" type="email" :value="old('email', $member['email'] ?? '')" />
                <x-form.text name="phone" label="Phone" :value="old('phone', $member['phone'] ?? '')" inputmode="tel" />
            </div>
            <x-form.segmented name="status" label="Status" :options="['ACTIVE' => 'Active', 'SUSPENDED' => 'Suspended', 'TERMINATED' => 'Ended']" :value="old('status', $member['status'] ?? 'ACTIVE')" />
        </x-form.section>
        <x-form.actions submit="Save profile" />
    </form>

    <x-form.section class="mt-8" title="Sign-in" description="How this person signs in. Secrets are never shown: enter a new one to replace it.">
        <div class="flex flex-wrap gap-2 text-sm">
            <x-badge :tone="($member['hasPassword'] ?? false) ? 'good' : 'default'">Password {{ ($member['hasPassword'] ?? false) ? 'set' : 'not set' }}</x-badge>
            <x-badge :tone="($member['hasPin'] ?? false) ? 'good' : 'default'">PIN {{ ($member['hasPin'] ?? false) ? 'set' : 'not set' }}</x-badge>
            <x-badge :tone="($member['hasNfcCard'] ?? false) ? 'good' : 'default'">Card {{ ($member['hasNfcCard'] ?? false) ? 'assigned' : 'none' }}</x-badge>
        </div>
        <form method="POST" action="{{ route('staff.credential', [$member['id'], 'password']) }}" class="grid gap-3" novalidate>@csrf @method('PUT')
            <x-form.password name="password" label="New password" :meter="true" hint="At least 8 characters. Use a phrase you can remember." />
            <div><x-btn variant="secondary">Set password</x-btn></div></form>
        <form method="POST" action="{{ route('staff.credential', [$member['id'], 'pin']) }}" class="grid gap-3" novalidate>@csrf @method('PUT')
            <x-form.pin name="pin" label="New PIN" :length="4" hint="4 to 8 digits, used on tablets and POS terminals." />
            <div><x-btn variant="secondary">Set PIN</x-btn></div></form>
        <form method="POST" action="{{ route('staff.credential', [$member['id'], 'nfc-card']) }}" class="grid gap-3" novalidate>@csrf @method('PUT')
            <x-form.text name="cardUid" label="NFC card" hint="Tap the card on a reader, or type its number." placeholder="e.g. 04:A2:19:7B" />
            <div class="flex flex-wrap gap-2"><x-btn variant="secondary">Assign card</x-btn></div></form>
        @if ($member['hasNfcCard'] ?? false)
            <form method="POST" action="{{ route('staff.card.remove', $member['id']) }}" x-data="confirmSubmit('Remove this NFC card?')" @submit="ask($event)">@csrf @method('DELETE')<x-btn variant="secondary" class="text-red-800">Remove card</x-btn></form>
        @endif
    </x-form.section>

    @if ($policy->state !== 'forbidden' && $policy->state !== 'pending')
        <x-form.section class="mt-8" title="Cash collected at tables" description="Whether this person may hold cash they collect from customers until they hand it over. Card machine and transfer collections are not affected." id="collection-policy">
            @if ($policy->ok())
                <div class="grid gap-4" data-testid="collection-policy">
                    @if (count($collectionFacilities) > 1)
                        <form method="GET" class="max-w-xs"><x-form.select name="policyFacility" label="Show effective policy at" :options="collect($collectionFacilities)->pluck('name', 'id')->all()" :value="$policyFacility" :searchable="false" x-on:change="$el.closest('form').submit()" /></form>
                    @endif
                    <div class="rounded-xl border border-stone-200 bg-stone-50 p-4 text-sm" data-testid="effective-policy">
                        <div class="font-semibold">Right now, at {{ collect($collectionFacilities)->firstWhere('id', $eff['facilityId'] ?? null)['name'] ?? 'this facility' }}</div>
                        <ul class="mt-1.5 space-y-1 text-stone-700">
                            <li>{{ ! empty($eff['collectionEnabled']) ? 'Waiters can collect payment at the table.' : 'Waiter collection is switched off at this facility.' }}</li>
                            <li>@if (! empty($eff['cashHolding']['allowed']))May hold cash{{ ! empty($eff['cashHolding']['limit']) && \App\Support\Money::cmp($eff['cashHolding']['limit'], '0') > 0 ? ' up to '.\App\Support\Money::format($eff['cashHolding']['limit']) : ' with no limit' }} <span class="text-stone-500">({{ ($eff['cashHolding']['source'] ?? '') === 'staff' ? 'set for this person' : 'from the facility rule' }})</span>.@else Cannot hold cash: customers are sent to the cashier. <span class="text-stone-500">({{ ($eff['cashHolding']['source'] ?? '') === 'staff' ? 'set for this person' : 'from the facility rule' }})</span>@endif</li>
                            <li>Can take: {{ collect($eff['allowedTenders'] ?? [])->map(fn ($t) => $tenderNames[$t] ?? $t)->implode(', ') ?: 'nothing' }}.</li>
                        </ul>
                    </div>
                    <form method="POST" action="{{ route('staff.collection-policy', $member['id']) }}" class="grid gap-4" x-data="{ mode: '{{ $ovr['cashHolding'] ?? 'INHERIT' }}' }" novalidate>@csrf @method('PATCH')
                        <input type="hidden" name="policyFacility" value="{{ $policyFacility }}">
                        <x-form.radio-cards name="cashHolding" label="For this person" x-model="mode" :value="$ovr['cashHolding'] ?? 'INHERIT'" :options="[
                            ['value' => 'INHERIT', 'label' => 'Follow the facility rule', 'description' => 'Use whatever the facility allows.', 'icon' => 'layers'],
                            ['value' => 'ALLOW', 'label' => 'Always allow', 'description' => 'May hold cash even where the facility does not allow it.', 'icon' => 'check'],
                            ['value' => 'DENY', 'label' => 'Never allow', 'description' => 'Must send every cash customer to the cashier.', 'icon' => 'ban']]" />
                        <div x-show="mode === 'ALLOW'" x-cloak><x-form.money name="cashLimit" label="Personal cash limit" :value="$ovr['cashLimit'] ?? null" :scale="2" :quick="['20000', '50000', '100000']" hint="They must hand over once they hold more than this. Leave empty to use the facility limit." /></div>
                        <x-form.actions submit="Save cash policy" />
                    </form>
                </div>
            @else<x-fetch :of="$policy" what="The cash collection policy" />@endif
        </x-form.section>
    @endif
    @if ($cashInHand->ok())
        <x-card title="Cash in hand" subtitle="What this person is holding from table collections." class="mt-8" data-testid="staff-cash-in-hand">
            <dl class="grid gap-x-8 gap-y-3 text-sm sm:grid-cols-4">
                <div><dt class="t-label">Holding now</dt><dd class="mt-1 text-xl font-semibold tabular-nums"><x-money :value="$cashInHand->data['cashInHand'] ?? '0'" /></dd></div>
                <div><dt class="t-label">Waiting for confirmation</dt><dd class="mt-1 text-xl font-semibold tabular-nums">{{ (int) ($cashInHand->data['pendingCollections'] ?? 0) }}</dd></div>
                <div><dt class="t-label">Handovers open</dt><dd class="mt-1 text-xl font-semibold tabular-nums">{{ (int) ($cashInHand->data['openHandovers'] ?? 0) }}</dd></div>
                <div><dt class="t-label">Short, not signed off</dt><dd class="mt-1 text-xl font-semibold tabular-nums"><x-money :value="$cashInHand->data['unsignedShortfall'] ?? '0'" /></dd></div>
            </dl>
        </x-card>
    @endif

    <x-card title="Roles and scopes" subtitle="What this person is allowed to do, and where." flush class="mt-8">
        <x-fetch :of="$assign" what="Role assignments" />
        @if ($assign->ok())
            <div class="table-scroll"><table class="data-table"><thead><tr><th>Role</th><th>Where</th><th>Granted</th><th class="w-12"></th></tr></thead><tbody>
            @forelse ($assign->items() as $a)
                @if (empty($a['revokedAt']))
                <tr><td class="font-medium">{{ $roleNames[$a['roleId'] ?? ''] ?? $a['roleCode'] ?? $a['roleId'] ?? '' }}</td><td>{{ ucfirst(strtolower($a['scopeType'] ?? '')) }}@if (($a['scopeType'] ?? '') === 'FACILITY' && ! empty($a['scopeId']))<div class="text-xs text-stone-500">{{ collect($facilities)->firstWhere('id', $a['scopeId'])['name'] ?? '' }}</div>@endif</td><td><x-time :at="$a['grantedAt'] ?? null" /></td>
                    <td class="text-right">@if (! empty($a['id']) && auth_staff()->can('role_assignment.manage'))<form method="POST" action="{{ route('staff.role.revoke', [$member['id'], $a['id']]) }}" x-data="confirmSubmit('Revoke this role?')" @submit="ask($event)">@csrf @method('DELETE')<button class="text-sm text-red-800 underline">Revoke</button></form>@endif</td></tr>
                @endif
            @empty<tr><td colspan="4"><x-empty title="No roles yet" text="Grant a role below so this person can do something." icon="shield" /></td></tr>@endforelse
            </tbody></table></div>
        @endif
        @if (auth_staff()->can('role_assignment.manage') && $roles->ok())
            @php
                $scopeOptions = [];
                ! empty($site['organizationId']) && $scopeOptions[$site['organizationId']] = 'Whole organization';
                ! empty($site['id']) && $scopeOptions[$site['id']] = 'Whole site';
                foreach ($facilities as $f) { $scopeOptions[$f['id'] ?? ''] = $f['name'] ?? ''; }
            @endphp
            <form method="POST" action="{{ route('staff.role.grant', $member['id']) }}" class="grid gap-4 border-t border-stone-100 p-5 sm:grid-cols-4 sm:items-end" novalidate>
                @csrf
                <x-form.select name="roleId" label="Role" :options="collect($roles->items())->pluck('name', 'id')->all()" required placeholder="Choose a role" />
                <x-form.select name="scopeType" label="Applies to" :options="['SITE' => 'Whole site', 'FACILITY' => 'One facility', 'ORGANIZATION' => 'Organization']" value="FACILITY" required :searchable="false" />
                <x-form.select name="scopeId" label="Which one" :options="$scopeOptions" required placeholder="Choose facility, site or organization" />
                <div><x-btn class="w-full">Grant role</x-btn></div>
            </form>
        @endif
    </x-card>

    <div class="grid gap-5 lg:grid-cols-2">
        <x-card title="Device assignments">
            <x-fetch :of="$devices" what="Devices" />
            @if ($devices->ok())
                <ul class="text-sm">@forelse ($myDevices as $d)<li class="flex justify-between py-1"><span>{{ $d['name'] ?? '' }} <span class="text-stone-500">({{ str_replace('_', ' ', $d['kind'] ?? '') }})</span></span><span class="text-xs text-stone-500">since <x-time :at="$d['checkout']['checkedOutAt'] ?? null" /></span></li>@empty<li class="text-stone-500">Not checked out to any device.</li>@endforelse</ul>
            @endif
        </x-card>
        <x-card title="Recent changes">
            <x-fetch :of="$audit" what="Audit trail" />
            @if ($audit->ok())
                <ul class="divide-y divide-stone-100 text-sm">@forelse ($audit->items() as $a)<li class="py-1.5"><b>{{ $a['action'] ?? '' }}</b> by {{ ($a['actorStaffId'] ?? null) ? ($names[$a['actorStaffId']] ?? \App\Services\Portal\Directory::short($a['actorStaffId'])) : 'system' }} <span class="text-xs text-stone-500"><x-time :at="$a['occurredAt'] ?? null" ago /></span></li>@empty<li class="text-stone-500">Nothing recorded.</li>@endforelse</ul>
            @endif
        </x-card>
    </div>
</x-layouts.app>
