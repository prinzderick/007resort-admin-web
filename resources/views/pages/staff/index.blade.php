<x-layouts.app title="Staff">
    <x-page-header title="Staff" subtitle="People, roles, cards and credentials. Permissions come from roles held at a scope." />
    <x-staff-nav />
    <form method="GET" class="mb-5 flex flex-wrap items-end gap-3">
        <div><label class="mb-1 block text-sm font-medium">Search</label><input name="q" value="{{ $q }}" class="min-h-11 rounded-lg border border-stone-300 px-3 text-sm" placeholder="Name or staff number"></div>
        <div><label class="mb-1 block text-sm font-medium">Status</label><select name="status" class="min-h-11 rounded-lg border border-stone-300 bg-white px-3 text-sm"><option value="">Any</option>@foreach (['ACTIVE', 'SUSPENDED', 'TERMINATED'] as $s)<option @selected($status === $s)>{{ $s }}</option>@endforeach</select></div>
        <x-btn variant="secondary">Filter</x-btn>
    </form>
    <x-card flush>
        <x-fetch :of="$staff" what="Staff directory" />
        @if ($staff->ok())
            <div class="overflow-x-auto"><table class="data-table" data-testid="staff-table"><thead><tr><th>No.</th><th>Name</th><th>Status</th><th>Sign-in methods</th><th></th></tr></thead><tbody>
            @forelse ($staff->items() as $m)
                <tr><td>{{ $m['staffNumber'] ?? '' }}</td><td class="font-medium">{{ $m['displayName'] }}<div class="text-xs font-normal text-stone-500">{{ $m['email'] ?? '' }}</div></td><td><x-badge :status="$m['status']" /></td>
                    <td class="text-xs">{{ implode(', ', array_filter([($m['hasPassword'] ?? false) ? 'password' : null, ($m['hasPin'] ?? false) ? 'PIN' : null, ($m['hasNfcCard'] ?? false) ? 'NFC card' : null])) ?: 'none' }}</td>
                    <td><a class="underline" href="{{ route('staff.show', $m['id']) }}">Manage</a></td></tr>
            @empty<tr><td colspan="5" class="text-center text-stone-500">No staff found.</td></tr>@endforelse
            </tbody></table></div>
        @endif
    </x-card>
    <x-card title="Add staff member">
        <form method="POST" action="{{ route('staff.store') }}" class="grid gap-x-4 sm:grid-cols-2">
            @csrf
            <x-field name="staffNumber" label="Staff number" required /><x-field name="email" label="Email" type="email" />
            <x-field name="firstName" label="First name" required /><x-field name="lastName" label="Last name" required />
            <x-field name="phone" label="Phone" />
            <div class="sm:col-span-2"><x-btn>Create</x-btn></div>
        </form>
    </x-card>
</x-layouts.app>
