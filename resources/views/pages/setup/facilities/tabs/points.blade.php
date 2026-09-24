<div class="grid gap-5 xl:grid-cols-2">
    <x-card title="Operating points" subtitle="Counters, table areas, gates and windows where staff work" flush>
        <x-fetch :of="$points" what="Operating points" />
        @if ($points->ok())
            <div class="overflow-x-auto"><table class="data-table" data-testid="points-table"><thead><tr><th>Code</th><th>Name</th><th>Kind</th></tr></thead><tbody>
            @forelse ($points->items() as $p)<tr><td class="text-xs">{{ $p['code'] ?? '' }}</td><td class="font-medium">{{ $p['name'] ?? '' }}</td><td>{{ str_replace('_', ' ', $p['kind'] ?? '') }}</td></tr>@empty<tr><td colspan="3"><x-empty title="No operating points" text="Add a counter or table area so devices can be assigned to it." icon="table" /></td></tr>@endforelse
            </tbody></table></div>
        @endif
    </x-card>
    <x-card title="Kitchen & bar stations" subtitle="Where this facility's orders are prepared" flush>
        <x-fetch :of="$stations" what="Stations" />
        @if ($stations->ok())
            <div class="overflow-x-auto"><table class="data-table"><thead><tr><th>Station</th><th>Kind</th><th>Active</th></tr></thead><tbody>
            @forelse ($stations->items() as $s)<tr><td class="font-medium">{{ $s['name'] ?? '' }}</td><td>{{ $s['kind'] ?? '' }}</td><td><x-badge :tone="($s['active'] ?? true) ? 'good' : 'default'">{{ ($s['active'] ?? true) ? 'Active' : 'Off' }}</x-badge></td></tr>@empty<tr><td colspan="3" class="text-center text-stone-500">None at this facility.</td></tr>@endforelse
            </tbody></table></div>
        @endif
    </x-card>
</div>
<x-card title="Dining tables" flush>
    <x-fetch :of="$tables" what="Tables" />
    @if ($tables->ok())
        <div class="flex flex-wrap gap-2 p-4">@forelse ($tables->items() as $t)<span class="rounded-lg border border-stone-200 px-3 py-1.5 text-sm">{{ $t['label'] ?? '?' }} <span class="text-xs text-stone-500">({{ $t['seats'] ?? '?' }} seats)</span></span>@empty<span class="text-sm text-stone-500">This facility has no dining tables.</span>@endforelse</div>
    @endif
</x-card>
<x-pending-api :items="['Adding or renaming operating points, tables and stations from this screen needs the API endpoints for them (operating-point and table management), which are not in the contract this portal was built against yet']" />
