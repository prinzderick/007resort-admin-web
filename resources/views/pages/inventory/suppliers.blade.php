<x-layouts.app title="Suppliers">
    <x-page-header title="Suppliers" subtitle="Who stock is bought from. Pick a supplier by name when receiving stock." />
    <x-card flush x-data="tableTools">
        <x-table-tools placeholder="Filter suppliers..." />
        <x-fetch :of="$suppliers" what="Suppliers" />
        @if ($suppliers->ok())
            <div class="overflow-x-auto"><table class="data-table" data-testid="suppliers-table"><thead><tr><th @click="sort(0)" data-sort>Supplier</th><th>Contact</th><th>Phone</th><th>Email</th><th>Active</th></tr></thead><tbody x-ref="body">
            @forelse ($suppliers->items() as $s)
                <tr data-row><td class="font-medium">{{ $s['name'] ?? '' }}<div class="text-xs font-normal text-stone-500">{{ $s['address'] ?? '' }}</div></td><td>{{ $s['contactName'] ?? '-' }}</td><td>{{ $s['phone'] ?? '-' }}</td><td>{{ $s['email'] ?? '-' }}</td><td><x-badge :tone="($s['active'] ?? true) ? 'good' : 'default'">{{ ($s['active'] ?? true) ? 'Active' : 'Inactive' }}</x-badge></td></tr>
            @empty<tr><td colspan="5"><x-empty title="No suppliers yet" text="Add the wholesalers and vendors you buy from." icon="truck" /></td></tr>@endforelse
            </tbody></table></div>
        @endif
    </x-card>
    @if ($canManage)
        <x-card title="Add a supplier">
            <form method="POST" action="{{ route('inventory.suppliers.store') }}" class="grid gap-x-4 sm:grid-cols-2" x-data="dirtyGuard">
                @csrf
                <x-field name="name" label="Supplier name" required /><x-field name="contactName" label="Contact person" />
                <x-field name="phone" label="Phone" /><x-field name="email" label="Email" type="email" />
                <div class="sm:col-span-2"><x-field name="address" label="Address" /></div>
                <div class="sm:col-span-2"><x-btn icon="plus">Add supplier</x-btn></div>
            </form>
        </x-card>
    @endif
</x-layouts.app>
