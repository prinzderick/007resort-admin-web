<x-layouts.app title="KDS routing">
    <x-page-header title="KDS routing" subtitle="Which prep station receives each product." />
    <x-card title="Stations" flush>
        <x-fetch :of="$stations" what="Stations" />
        <x-fetch :of="$products" what="Product routing" />
        @if ($stations->ok())<div class="overflow-x-auto"><table class="data-table" data-testid="stations"><thead><tr><th>Station</th><th>Kind</th><th>Active</th><th>Products routed</th></tr></thead><tbody>
        @forelse ($stations->items() as $s)<tr><td class="font-medium">{{ $s['name'] ?? '' }}</td><td>{{ $s['kind'] ?? '' }}</td><td><x-badge :tone="($s['active'] ?? true) ? 'good' : 'default'">{{ ($s['active'] ?? true) ? 'Active' : 'Off' }}</x-badge></td><td class="text-sm">{{ implode(', ', $byStation[$s['name'] ?? ''] ?? []) ?: '-' }}</td></tr>@empty<tr><td colspan="4" class="text-center text-stone-500">No stations.</td></tr>@endforelse
        </tbody></table></div>@endif
    </x-card>
    <x-card title="Prep routes" flush>
        <x-fetch :of="$routes" what="Prep routes" />
        @if ($routes->ok())<ul class="flex flex-wrap gap-2 p-4 text-sm">@forelse ($routes->items() as $r)<li class="rounded-lg border border-stone-200 px-3 py-1.5">{{ $r['name'] ?? '' }} <span class="text-xs text-stone-500">{{ $r['kind'] ?? '' }}</span></li>@empty<li class="text-stone-500">None.</li>@endforelse</ul>@endif
    </x-card>
    <x-pending-api :items="['Changing a product\'s prep route: choose the prep route on the product form under Products, prices &amp; categories (needs catalog.manage)', 'Create / disable KDS stations and per-operating-point default stations (no endpoint in the contract)']" />
</x-layouts.app>
