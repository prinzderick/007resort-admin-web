@php
    $pointList = collect($points->items());
    $sections = $pointList->where('kind', 'TABLE_AREA')->values();
    $sectionOptions = $sections->where('active', true)->map(fn ($p) => ['value' => $p['id'], 'label' => $p['name']])->values()->all();
    $stationOptions = $pointList->filter(fn ($p) => ! empty($p['kdsStation']) && $p['active'])->map(fn ($p) => ['value' => $p['id'], 'label' => $p['name']])->values()->all();
    $routeOptions = collect($prepRoutes->items())->map(fn ($r) => ['value' => $r['id'], 'label' => $r['name'].' ('.strtolower($r['kind']).')'])->values()->all();
    $pointName = $pointList->pluck('name', 'id')->all();
    $tableList = collect($tables->items())->sortBy(fn ($t) => [$t['sortOrder'] ?? 0, strlen($t['label'] ?? ''), $t['label'] ?? ''])->values();
    $ver = fn ($row) => $row['rowVersion'] ?? '';
@endphp
<x-card title="Operating points" subtitle="The places where staff work: counters, table areas, gates, store windows and the kitchen / bar screens. Devices are assigned to one." flush data-testid="points-card">
    <x-slot:aside>@if ($canEdit)<x-btn type="button" icon="plus" @click="$dispatch('open-modal', 'add-point')" data-testid="add-point">Add operating point</x-btn>@endif</x-slot:aside>
    <x-fetch :of="$points" what="Operating points" />
    @if ($points->ok())
        <div class="table-scroll"><table class="data-table" data-testid="points-table"><thead><tr><th>Name</th><th>Kind</th><th>Prepares for</th><th>Status</th><th class="w-12"></th></tr></thead><tbody>
        @forelse ($points->items() as $p)
            @php $ks = $p['kdsStation'] ?? null; $route = $ks ? collect($prepRoutes->items())->firstWhere('id', $ks['prepRouteId'] ?? null) : null; @endphp
            <tr>
                <td><div class="font-medium">{{ $p['name'] ?? '' }}</div><div class="text-xs text-stone-500">{{ $p['code'] ?? '' }}</div></td>
                <td>{{ $pointKinds[$p['kind'] ?? ''] ?? ucfirst(strtolower(str_replace('_', ' ', $p['kind'] ?? ''))) }}</td>
                <td class="text-sm text-stone-600">@if ($ks){{ $kdsKinds[$ks['kind'] ?? ''] ?? $ks['kind'] }} screen @if ($route) &middot; {{ $route['name'] }} orders @endif @elseif (! empty($p['defaultPrepStationId']))Sends to {{ $pointName[$p['defaultPrepStationId']] ?? 'a station' }}@else <span class="text-stone-400">-</span>@endif</td>
                <td><x-badge :tone="($p['active'] ?? true) ? 'good' : 'default'">{{ ($p['active'] ?? true) ? 'Active' : 'Switched off' }}</x-badge></td>
                <td class="text-right">@if ($canEdit)<x-row-menu>
                    <button type="button" @click="$dispatch('open-modal', 'edit-point-{{ $p['id'] }}')">Edit</button>
                    <form method="POST" action="{{ route('setup.points.state', [$p['id'], ($p['active'] ?? true) ? 'deactivate' : 'reactivate']) }}">@csrf<input type="hidden" name="facilityId" value="{{ $id }}"><input type="hidden" name="rowVersion" value="{{ $ver($p) }}"><button class="w-full text-left {{ ($p['active'] ?? true) ? 'text-red-800' : '' }}">{{ ($p['active'] ?? true) ? 'Switch off' : 'Switch on' }}</button></form>
                </x-row-menu>@endif</td>
            </tr>
        @empty
            <tr><td colspan="5"><x-empty title="No operating points yet" text="Add a counter or a table area so devices can be assigned to it." icon="table" /></td></tr>
        @endforelse
        </tbody></table></div>
    @endif
</x-card>

@if ($canEdit)
    <x-dialog name="add-point" title="Add operating point" subtitle="Where staff work at {{ $f['name'] ?? 'this facility' }}.">
        <form method="POST" action="{{ route('setup.points.store', $id) }}" class="grid gap-4" x-data="{ kind: 'COUNTER' }" novalidate>@csrf
            <x-form.text name="name" label="Name" required placeholder="e.g. Terrace counter" />
            <x-form.text name="code" label="Code" required hint="Letters, numbers and underscores, e.g. TERRACE_1. Cannot be changed later." />
            <x-form.select name="kind" label="Kind" :options="$pointKinds" value="COUNTER" x-model="kind" :searchable="false" />
            <div x-show="kind === 'STATION'" x-cloak class="grid gap-4">
                <x-form.segmented name="kdsKind" label="Screen type" :options="$kdsKinds" value="KITCHEN" />
                <x-form.select name="prepRouteId" label="Prepares" :options="$routeOptions" placeholder="Default for this screen type" :clearable="true" hint="Orders on this route show on this screen." />
            </div>
            <div class="flex justify-end gap-2"><x-btn type="button" variant="secondary" @click="open = false">Cancel</x-btn><x-btn>Add operating point</x-btn></div>
        </form>
    </x-dialog>
    @foreach ($points->items() as $p)
        <x-dialog name="edit-point-{{ $p['id'] }}" title="Edit {{ $p['name'] ?? 'operating point' }}" subtitle="Code {{ $p['code'] ?? '' }} and kind cannot be changed.">
            <form method="POST" action="{{ route('setup.points.update', $p['id']) }}" class="grid gap-4" novalidate>@csrf @method('PATCH')
                <input type="hidden" name="facilityId" value="{{ $id }}"><input type="hidden" name="rowVersion" value="{{ $ver($p) }}">
                <x-form.text name="name" label="Name" required :value="$p['name'] ?? ''" />
                @if (! empty($p['kdsStation']))
                    <x-form.select name="prepRouteId" label="Prepares" :options="$routeOptions" :value="$p['kdsStation']['prepRouteId'] ?? null" />
                @endif
                @if (($p['kind'] ?? '') !== 'STATION' && $stationOptions !== [])
                    <x-form.select name="defaultPrepStationId" label="Default kitchen / bar screen" :options="collect($stationOptions)->reject(fn ($o) => $o['value'] === $p['id'])->values()->all()" :value="$p['defaultPrepStationId'] ?? null" :clearable="true" placeholder="None" />
                @endif
                <div class="flex justify-end gap-2"><x-btn type="button" variant="secondary" @click="open = false">Cancel</x-btn><x-btn>Save</x-btn></div>
            </form>
        </x-dialog>
    @endforeach
@endif

<x-card title="Dining tables" subtitle="Numbered tables that waiters open and attach orders to." flush data-testid="tables-card">
    <x-slot:aside>@if ($canEdit)<div class="flex flex-wrap gap-2"><x-btn type="button" variant="secondary" icon="plus" @click="$dispatch('open-modal', 'bulk-tables')" data-testid="bulk-tables">Add many</x-btn><x-btn type="button" icon="plus" @click="$dispatch('open-modal', 'add-table')" data-testid="add-table">Add table</x-btn></div>@endif</x-slot:aside>
    <x-fetch :of="$tables" what="Tables" />
    @if ($tables->ok())
        <div class="table-scroll"><table class="data-table" data-testid="tables-table"><thead><tr><th>Table</th><th>Area</th><th class="num">Seats</th><th>Status</th><th class="w-12"></th></tr></thead><tbody>
        @forelse ($tableList as $t)
            <tr>
                <td class="font-medium">{{ $t['label'] ?? '?' }}@if (! empty($t['mergedTableIds']))<span class="ml-2 text-xs font-normal text-stone-500">joined with {{ count($t['mergedTableIds']) }} more</span>@endif @if (! empty($t['mergedIntoId']))<span class="ml-2 text-xs font-normal text-stone-500">part of another table</span>@endif</td>
                <td class="text-stone-600">{{ $pointName[$t['operatingPointId'] ?? ''] ?? '-' }}</td>
                <td class="num">{{ $t['effectiveSeats'] ?? $t['seats'] ?? '' }}</td>
                <td>@if (! ($t['active'] ?? true))<x-badge>Switched off</x-badge>@else<x-badge :status="$t['status'] ?? 'FREE'" />@endif</td>
                <td class="text-right">@if ($canEdit)<x-row-menu>
                    <button type="button" @click="$dispatch('open-modal', 'edit-table-{{ $t['id'] }}')">Edit</button>
                    @if (! empty($t['mergedTableIds']))<form method="POST" action="{{ route('setup.tables.state', [$t['id'], 'unmerge']) }}">@csrf<input type="hidden" name="facilityId" value="{{ $id }}"><input type="hidden" name="rowVersion" value="{{ $ver($t) }}"><button class="w-full text-left">Separate tables</button></form>@endif
                    <form method="POST" action="{{ route('setup.tables.state', [$t['id'], ($t['active'] ?? true) ? 'deactivate' : 'reactivate']) }}">@csrf<input type="hidden" name="facilityId" value="{{ $id }}"><input type="hidden" name="rowVersion" value="{{ $ver($t) }}"><button class="w-full text-left {{ ($t['active'] ?? true) ? 'text-red-800' : '' }}">{{ ($t['active'] ?? true) ? 'Switch off' : 'Switch on' }}</button></form>
                </x-row-menu>@endif</td>
            </tr>
        @empty
            <tr><td colspan="5"><x-empty title="This facility has no dining tables" text="Add tables one by one, or a whole run at once (T1 to T20)." icon="table" /></td></tr>
        @endforelse
        </tbody></table></div>
    @endif
</x-card>

@if ($canEdit)
    <x-dialog name="add-table" title="Add a table">
        <form method="POST" action="{{ route('setup.tables.store', $id) }}" class="grid gap-4" novalidate>@csrf
            <x-form.text name="label" label="Table label" required placeholder="e.g. T21" />
            <x-form.stepper name="seats" label="Seats" :value="4" :min="1" :max="200" />
            @if ($sectionOptions !== [])<x-form.select name="operatingPointId" label="Area" :options="$sectionOptions" :clearable="true" placeholder="No area" />@endif
            <div class="flex justify-end gap-2"><x-btn type="button" variant="secondary" @click="open = false">Cancel</x-btn><x-btn>Add table</x-btn></div>
        </form>
    </x-dialog>
    <x-dialog name="bulk-tables" title="Add many tables" subtitle="Creates a numbered run. Labels that already exist are skipped and listed.">
        <form method="POST" action="{{ route('setup.tables.bulk', $id) }}" class="grid gap-4" x-data="{ prefix: 'T', from: 1, to: 20, pad: 0, get preview() { const n = Math.max(0, this.to - this.from + 1); if (!n || n > 500) return n > 500 ? 'At most 500 tables at once.' : ''; const l = i => this.prefix + String(i).padStart(this.pad, '0'); return n + ' tables: ' + (n > 3 ? l(this.from) + ', ' + l(this.from + 1) + ' ... ' + l(this.to) : Array.from({length: n}, (_, i) => l(this.from + i)).join(', ')) } }" novalidate>@csrf
            <div class="grid gap-4 sm:grid-cols-3">
                <x-form.text name="prefix" label="Prefix" value="T" x-model="prefix" :maxlength="12" />
                <x-form.stepper name="from" label="From" :value="1" :min="0" :max="9999" x-model.number="from" />
                <x-form.stepper name="to" label="To" :value="20" :min="0" :max="9999" x-model.number="to" />
            </div>
            <div class="grid gap-4 sm:grid-cols-2">
                <x-form.stepper name="seats" label="Seats per table" :value="4" :min="1" :max="200" />
                <x-form.segmented name="padWidth" label="Number format" :options="['0' => 'T1', '2' => 'T01', '3' => 'T001']" value="0" x-model="pad" />
            </div>
            @if ($sectionOptions !== [])<x-form.select name="operatingPointId" label="Area" :options="$sectionOptions" :clearable="true" placeholder="No area" />@endif
            <p class="rounded-lg bg-stone-50 px-3 py-2 text-sm text-stone-700" x-text="preview" data-testid="bulk-preview"></p>
            <div class="flex justify-end gap-2"><x-btn type="button" variant="secondary" @click="open = false">Cancel</x-btn><x-btn>Create tables</x-btn></div>
        </form>
    </x-dialog>
    @foreach ($tableList as $t)
        <x-dialog name="edit-table-{{ $t['id'] }}" title="Edit table {{ $t['label'] ?? '' }}">
            <form method="POST" action="{{ route('setup.tables.update', $t['id']) }}" class="grid gap-4" novalidate>@csrf @method('PATCH')
                <input type="hidden" name="facilityId" value="{{ $id }}"><input type="hidden" name="rowVersion" value="{{ $ver($t) }}">
                <x-form.text name="label" label="Table label" required :value="$t['label'] ?? ''" />
                <x-form.stepper name="seats" label="Seats" :value="(int) ($t['seats'] ?? 4)" :min="1" :max="200" />
                @if ($sectionOptions !== [])<x-form.select name="operatingPointId" label="Area" :options="$sectionOptions" :value="$t['operatingPointId'] ?? null" :clearable="true" placeholder="No area" />@endif
                <div class="flex justify-end gap-2"><x-btn type="button" variant="secondary" @click="open = false">Cancel</x-btn><x-btn>Save</x-btn></div>
            </form>
            @php $free = $tableList->filter(fn ($o) => $o['id'] !== $t['id'] && ($o['active'] ?? true) && empty($o['mergedIntoId']) && empty($t['mergedIntoId']))->map(fn ($o) => ['value' => $o['id'], 'label' => $o['label']])->values()->all(); @endphp
            @if ($free !== [])
                <form method="POST" action="{{ route('setup.tables.merge', $t['id']) }}" class="mt-5 grid gap-3 border-t border-stone-100 pt-4" novalidate>@csrf
                    <input type="hidden" name="facilityId" value="{{ $id }}"><input type="hidden" name="rowVersion" value="{{ $ver($t) }}">
                    <x-form.select name="intoTableId" label="Join with another table" :options="$free" hint="Both tables must be free. Their seats count together until you separate them." :clearable="true" placeholder="Choose a table" />
                    <div><x-btn variant="secondary">Join tables</x-btn></div>
                </form>
            @endif
        </x-dialog>
    @endforeach
@endif
