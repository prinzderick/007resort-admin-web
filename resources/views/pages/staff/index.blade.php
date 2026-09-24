<x-layouts.app title="Staff">
    <x-page-header title="Staff" subtitle="People, their roles, cards and sign-in. What someone may do comes from the roles they hold and where they hold them.">
        <x-slot:actions><x-btn variant="secondary" :href="route('people.roles')" icon="shield">Roles &amp; permissions</x-btn><x-btn type="button" icon="plus" @click="$dispatch('open-modal', 'add-staff')" data-testid="add-staff">Add staff member</x-btn></x-slot:actions>
    </x-page-header>
    <x-filter-form :reset="route('staff.index')">
        <x-filter-text name="q" label="Search" :value="$q" placeholder="Name or staff number" />
        <x-filter-select name="status" label="Status" :options="['ACTIVE' => 'Active', 'SUSPENDED' => 'Suspended', 'TERMINATED' => 'Ended']" :value="$status" all="Any status" width="10rem" />
    </x-filter-form>
    <x-card flush x-data="tableTools">
        <x-table-tools />
        <x-fetch :of="$staff" what="Staff directory" />
        @if ($staff->ok())
            <div class="table-scroll"><table class="data-table" data-testid="staff-table"><thead><tr><th>No.</th><th>Name</th><th>Status</th><th>Sign-in</th><th class="w-12"></th></tr></thead><tbody>
            @forelse ($staff->items() as $m)
                @php $flags = array_filter([($m['hasPassword'] ?? null) ? 'password' : null, ($m['hasPin'] ?? null) ? 'PIN' : null, ($m['hasNfcCard'] ?? null) ? 'card' : null]); $known = array_key_exists('hasPassword', $m) || array_key_exists('hasPin', $m) || array_key_exists('hasNfcCard', $m); @endphp
                <tr data-row><td class="font-mono text-xs text-stone-600">{{ $m['staffNumber'] ?? '' }}</td>
                    <td><a class="font-medium text-brand-700 underline decoration-brand-200 underline-offset-2" href="{{ route('staff.show', $m['id'] ?? '') }}">{{ $m['displayName'] ?? trim(($m['firstName'] ?? '').' '.($m['lastName'] ?? '')) }}</a><div class="text-xs text-stone-500">{{ $m['email'] ?? '' }}</div></td>
                    <td><x-badge :status="$m['status'] ?? 'UNKNOWN'" /></td>
                    <td class="text-sm text-stone-600">{{ $known ? (implode(', ', $flags) ?: 'Nothing set') : '-' }}</td>
                    <td class="text-right">@if (! empty($m['id']))<a class="underline" href="{{ route('staff.show', $m['id']) }}">Manage</a>@endif</td></tr>
            @empty<tr><td colspan="5"><x-empty title="No staff found" text="Change the search, or add the first staff member." icon="user" /></td></tr>@endforelse
            </tbody></table></div>
        @endif
    </x-card>
    <x-dialog name="add-staff" title="Add a staff member" subtitle="You set their password or PIN and give them a role on the next screen.">
        <form method="POST" action="{{ route('staff.store') }}" class="grid gap-4" novalidate>@csrf
            <div class="grid gap-4 sm:grid-cols-2">
                <x-form.text name="firstName" label="First name" required />
                <x-form.text name="lastName" label="Last name" required />
                <x-form.text name="staffNumber" label="Staff number" required hint="Printed on their badge, e.g. S-0014." />
                <x-form.text name="phone" label="Phone" inputmode="tel" />
            </div>
            <x-form.text name="email" label="Email" type="email" />
            <div class="flex justify-end gap-2"><x-btn type="button" variant="secondary" @click="open = false">Cancel</x-btn><x-btn>Create staff member</x-btn></div>
        </form>
    </x-dialog>
</x-layouts.app>
