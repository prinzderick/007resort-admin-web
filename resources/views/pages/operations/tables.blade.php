<x-layouts.app title="Tables">
    <x-page-header title="Tables" subtitle="Live floor plan status for one facility: who is seated, which tables have open orders or tabs." />
    <form method="GET" class="mb-5 flex items-end gap-3"><div><label class="mb-1 block text-xs font-medium text-stone-600">Facility</label><select name="facility" class="min-h-10 rounded-lg border border-stone-300 bg-white px-3 text-sm" onchange="this.form.submit()">@foreach ($facilities as $f)<option value="{{ $f['id'] ?? '' }}" @selected($facilityId === ($f['id'] ?? ''))>{{ $f['name'] ?? '' }}</option>@endforeach</select></div></form>
    <x-fetch :of="$tables" what="Tables" />
    @if ($tables->ok())
        @php
            $counts = collect($tables->items())->countBy(fn ($t) => $t['status'] ?? 'UNKNOWN');
            $tone = ['FREE' => 'border-brand-200 bg-brand-50', 'OCCUPIED' => 'border-amber-300 bg-amber-50', 'RESERVED' => 'border-sky-200 bg-sky-50', 'DIRTY' => 'border-stone-300 bg-stone-100'];
        @endphp
        <div class="mb-4 flex flex-wrap gap-2 text-sm">@foreach ($counts as $s => $n)<span class="rounded-lg border border-stone-200 bg-white px-3 py-1.5"><x-badge :status="$s" /> <b class="ml-1 tabular-nums">{{ $n }}</b></span>@endforeach</div>
        @if ($tables->items() === [])
            <x-card><x-empty title="No tables at this facility" text="Tables are set up per dining facility. Pick a restaurant, club or bar." icon="table" /></x-card>
        @else
            <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-5 xl:grid-cols-6" data-testid="tables-grid">
                @foreach ($tables->items() as $t)
                    <div class="rounded-xl border p-3 text-sm shadow-sm {{ $tone[$t['status'] ?? ''] ?? 'border-stone-200 bg-white' }}">
                        <div class="flex items-center justify-between"><span class="text-lg font-semibold">{{ $t['label'] ?? '?' }}</span><x-badge :status="$t['status'] ?? 'UNKNOWN'" /></div>
                        <div class="mt-1 text-xs text-stone-600">{{ $t['seats'] ?? '?' }} seats</div>
                        @if (! empty($t['openOrderIds']))<div class="mt-1 text-xs">{{ count($t['openOrderIds']) }} open order(s)</div>@endif
                        @if (! empty($t['openTabId']))<div class="text-xs">Open tab</div>@endif
                        @if (! empty($t['occupiedByStaffId']))<div class="mt-1 text-xs text-stone-500">Served by {{ $staffNames[$t['occupiedByStaffId']] ?? \App\Services\Portal\Directory::short($t['occupiedByStaffId']) }}</div>@endif
                    </div>
                @endforeach
            </div>
        @endif
    @endif
</x-layouts.app>
