<x-layouts.app :title="$member['displayName']">
    <x-page-header :title="$member['displayName']" :subtitle="'Staff no. '.($member['staffNumber'] ?? '-')">
        <x-slot:actions><x-btn variant="secondary" :href="route('staff.index')">Directory</x-btn></x-slot:actions>
    </x-page-header>
    <div class="grid gap-5 lg:grid-cols-2">
        <x-card title="Profile">
            <form method="POST" action="{{ route('staff.update', $member['id']) }}">
                @csrf @method('PATCH')
                <input type="hidden" name="etag" value="{{ $etag }}">
                <div class="grid gap-x-4 sm:grid-cols-2">
                    <x-field name="firstName" label="First name" :value="$member['firstName'] ?? ''" required /><x-field name="lastName" label="Last name" :value="$member['lastName'] ?? ''" required />
                    <x-field name="email" label="Email" type="email" :value="$member['email'] ?? ''" /><x-field name="phone" label="Phone" :value="$member['phone'] ?? ''" />
                </div>
                <x-field name="status" label="Status" :options="['ACTIVE' => 'Active', 'SUSPENDED' => 'Suspended', 'TERMINATED' => 'Terminated']" :value="$member['status']" required />
                <x-btn>Save changes</x-btn>
            </form>
        </x-card>

        <x-card title="Sign-in credentials">
            <ul class="mb-3 text-sm">
                <li>Password: <x-badge :tone="($member['hasPassword'] ?? false) ? 'good' : 'default'">{{ ($member['hasPassword'] ?? false) ? 'set' : 'not set' }}</x-badge></li>
                <li class="mt-1">PIN: <x-badge :tone="($member['hasPin'] ?? false) ? 'good' : 'default'">{{ ($member['hasPin'] ?? false) ? 'set' : 'not set' }}</x-badge></li>
                <li class="mt-1">NFC card: <x-badge :tone="($member['hasNfcCard'] ?? false) ? 'good' : 'default'">{{ ($member['hasNfcCard'] ?? false) ? 'assigned' : 'none' }}</x-badge></li>
            </ul>
            <form method="POST" action="{{ route('staff.credential', [$member['id'], 'nfc-card']) }}" class="mb-3 flex items-end gap-2">@csrf @method('PUT')
                <div class="flex-1"><x-field name="cardUid" label="NFC card UID (scan or type)" /></div><x-btn class="mb-3">Assign card</x-btn></form>
            @if ($member['hasNfcCard'] ?? false)
                <form method="POST" action="{{ route('staff.card.remove', $member['id']) }}" class="mb-3">@csrf @method('DELETE')<x-btn variant="secondary" onclick="return confirm('Remove this NFC card?')">Remove card</x-btn></form>
            @endif
            <form method="POST" action="{{ route('staff.credential', [$member['id'], 'password']) }}" class="mb-1 flex items-end gap-2">@csrf @method('PUT')
                <div class="flex-1"><x-field name="password" label="New password (min 10 characters)" type="password" /></div><x-btn class="mb-3">Set</x-btn></form>
            <form method="POST" action="{{ route('staff.credential', [$member['id'], 'pin']) }}" class="flex items-end gap-2">@csrf @method('PUT')
                <div class="flex-1"><x-field name="pin" label="New PIN (4-8 digits)" type="password" /></div><x-btn class="mb-3">Set</x-btn></form>
        </x-card>
    </div>

    <x-card title="Roles and scopes" flush>
        <x-fetch :of="$assign" what="Role assignments" />
        @if ($assign->ok())
            <div class="overflow-x-auto"><table class="data-table"><thead><tr><th>Role</th><th>Scope</th><th>Granted</th><th></th></tr></thead><tbody>
            @forelse ($assign->items() as $a)
                @if (empty($a['revokedAt']))
                <tr><td>{{ $roleNames[$a['roleId']] ?? $a['roleId'] }}</td><td>{{ $a['scopeType'] }}</td><td><x-time :at="$a['grantedAt'] ?? null" /></td>
                    <td>@if (auth_staff()->can('role_assignment.manage'))<form method="POST" action="{{ route('staff.role.revoke', [$member['id'], $a['id']]) }}">@csrf @method('DELETE')<button class="text-sm text-red-800 underline" onclick="return confirm('Revoke this role?')">Revoke</button></form>@endif</td></tr>
                @endif
            @empty<tr><td colspan="4" class="text-center text-stone-500">No roles assigned.</td></tr>@endforelse
            </tbody></table></div>
        @endif
        @if (auth_staff()->can('role_assignment.manage') && $roles->ok())
            <form method="POST" action="{{ route('staff.role.grant', $member['id']) }}" class="grid gap-x-3 border-t border-stone-100 p-4 sm:grid-cols-4">
                @csrf
                <x-field name="roleId" label="Role" :options="['' => 'Choose...'] + collect($roles->items())->pluck('name', 'id')->all()" required />
                <x-field name="scopeType" label="Scope" :options="['SITE' => 'Whole site', 'FACILITY' => 'One facility', 'ORGANIZATION' => 'Organization']" required />
                <x-field name="scopeId" label="Scope target" :options="collect($facilities)->pluck('name', 'id')->prepend('(site)', $site['id'] ?? '')->all()" required hint="Facility for FACILITY scope; the site entry for SITE scope." />
                <div class="flex items-end"><x-btn class="mb-3 w-full">Grant</x-btn></div>
            </form>
        @endif
    </x-card>

    <div class="grid gap-5 lg:grid-cols-2">
        <x-card title="Device assignments">
            <x-fetch :of="$devices" what="Devices" />
            @if ($devices->ok())
                <ul class="text-sm">@forelse ($myDevices as $d)<li class="flex justify-between py-1"><span>{{ $d['name'] }} <span class="text-stone-500">({{ str_replace('_', ' ', $d['kind']) }})</span></span><span class="text-xs text-stone-500">since <x-time :at="$d['checkout']['checkedOutAt']" /></span></li>@empty<li class="text-stone-500">Not checked out to any device.</li>@endforelse</ul>
            @endif
        </x-card>
        <x-card title="Recent audit entries">
            <x-fetch :of="$audit" what="Audit trail" />
            @if ($audit->ok())
                <ul class="divide-y divide-stone-100 text-sm">@forelse ($audit->items() as $a)<li class="py-1.5"><b>{{ $a['action'] }}</b> by {{ $a['actorName'] ?? 'system' }} <span class="text-xs text-stone-500"><x-time :at="$a['occurredAt']" ago /></span></li>@empty<li class="text-stone-500">Nothing recorded.</li>@endforelse</ul>
            @endif
        </x-card>
    </div>
</x-layouts.app>
