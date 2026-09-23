<x-layouts.app title="KDS routing">
    <x-page-header title="KDS routing" subtitle="Which prep station receives each product." />
    <x-config-nav />
    <x-card title="Stations" flush>
        <x-fetch :of="$stations" what="Stations" />
        @if ($stations->ok())<div class="overflow-x-auto"><table class="data-table"><thead><tr><th>Station</th><th>Kind</th><th>Active</th><th>Products routed</th></tr></thead><tbody>
        @forelse ($stations->items() as $s)<tr><td class="font-medium">{{ $s['name'] }}</td><td>{{ $s['kind'] }}</td><td><x-badge :tone="($s['active'] ?? true) ? 'good' : 'default'">{{ ($s['active'] ?? true) ? 'Active' : 'Off' }}</x-badge></td><td class="text-sm">{{ implode(', ', $byStation[$s['name']] ?? []) ?: '-' }}</td></tr>@empty<tr><td colspan="4" class="text-center text-stone-500">No stations.</td></tr>@endforelse
        </tbody></table></div>@endif
    </x-card>
    <x-pending-api :items="['Change a product\'s prep route or default station per operating point (no write endpoint in the contract)', 'Create / disable KDS stations']" />
</x-layouts.app>
