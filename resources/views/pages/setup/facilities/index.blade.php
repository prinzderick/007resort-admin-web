<x-layouts.app title="Facilities">
    <x-page-header title="Facilities" subtitle="Every place that sells, books or checks people in: restaurants, bars, pools, courts, spa, stores, gates. Click a facility to edit its details, capabilities, operating rules, operating points, devices and products." :crumbs="['Setup' => route('setup.index'), 'Facilities' => null]">
        <x-slot:actions>
            @if ($canAdd && $canManage)
                <x-btn icon="plus" :href="route('setup.facilities.create')" data-testid="add-facility">Add facility</x-btn>
            @elseif ($canManage)
                <span title="Adding facilities needs the API endpoint POST /organization/facilities, which is not in the contract this portal was built against yet"><x-btn type="button" icon="plus" disabled class="cursor-not-allowed opacity-50" data-testid="add-facility">Add facility</x-btn></span>
            @endif
        </x-slot:actions>
    </x-page-header>
    <x-settings-meta entity-type="Facility" />
    <x-card flush x-data="tableTools">
        <x-table-tools placeholder="Filter facilities..." />
        <x-fetch :of="$tree" what="Facilities" />
        @if ($tree->ok())
            <div class="overflow-x-auto"><table class="data-table" data-testid="facility-list">
                <thead><tr><th>Facility</th><th>Kind</th><th>Status</th><th>Capabilities</th><th></th></tr></thead>
                <tbody x-ref="body">
                @forelse ($rows as $r)
                    <tr data-row>
                        <td><div style="padding-left: {{ ($r['_depth'] ?? 0) * 1.25 }}rem" class="flex items-center gap-2">@if (($r['_depth'] ?? 0) > 0)<span class="text-stone-300">&boxur;</span>@endif<a class="font-medium text-brand-700 underline decoration-brand-200 underline-offset-2" href="{{ route('setup.facilities.show', $r['id'] ?? '') }}">{{ $r['name'] ?? '' }}</a><span class="text-xs text-stone-400">{{ $r['code'] ?? '' }}</span></div></td>
                        <td>{{ str_replace('_', ' ', $r['kind'] ?? '') }}</td><td><x-badge :status="$r['status'] ?? 'UNKNOWN'" /></td>
                        <td class="max-w-md text-xs text-stone-500">{{ implode(', ', array_map(fn ($c) => ucwords(strtolower(str_replace('_', ' ', $c))), array_slice((array) ($r['capabilities'] ?? []), 0, 6))) }}@if (count((array) ($r['capabilities'] ?? [])) > 6) +{{ count($r['capabilities']) - 6 }} more @endif</td>
                        <td class="text-right"><a class="text-sm font-medium text-brand-700 underline" href="{{ route('setup.facilities.show', ['facility' => $r['id'] ?? '', 'tab' => 'rules']) }}">Rules</a></td></tr>
                @empty
                    <tr><td colspan="5"><x-empty title="No facilities yet" text="Add your first facility: pick a template (restaurant, bar, pool, spa...) and it is set up with sensible defaults you can change." icon="building" :action="$canAdd ? 'Add facility' : null" :href="route('setup.facilities.create')" /></td></tr>
                @endforelse
                </tbody></table></div>
        @endif
    </x-card>
</x-layouts.app>
