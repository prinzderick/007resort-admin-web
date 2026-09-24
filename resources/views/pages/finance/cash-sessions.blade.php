<x-layouts.app title="Cash sessions" :range="true">
    <x-page-header title="Cash sessions" subtitle="Every cashier shift: float, expected cash, what was counted, and the difference. A shortage is flagged so it cannot be missed." />
    <x-card flush x-data="tableTools">
        <x-table-tools :csv="true" placeholder="Filter this page (cashier, facility...)">
            <form method="GET" class="flex flex-wrap items-end gap-3">
                @foreach (request()->only(['from', 'to', 'range']) as $k => $v)<input type="hidden" name="{{ $k }}" value="{{ $v }}">@endforeach
                <div><label class="mb-1 block text-xs font-medium text-stone-600">Facility</label><select name="facility" class="min-h-10 rounded-lg border border-stone-300 bg-white px-2 text-sm"><option value="">All</option>@foreach ($facilities as $f)<option value="{{ $f['id'] ?? '' }}" @selected($facilityId === ($f['id'] ?? ''))>{{ $f['name'] ?? '' }}</option>@endforeach</select></div>
                <div><label class="mb-1 block text-xs font-medium text-stone-600">Status</label><select name="status" class="min-h-10 rounded-lg border border-stone-300 bg-white px-2 text-sm"><option value="">Any</option>@foreach (['OPEN', 'CLOSED'] as $s)<option @selected($status === $s)>{{ $s }}</option>@endforeach</select></div>
                <x-btn variant="secondary">Apply</x-btn>
            </form>
        </x-table-tools>
        <x-fetch :of="$sessions" what="Cash sessions" />
        @if ($sessions->ok())
            <div class="table-scroll"><table class="data-table" data-testid="sessions-table">
                <thead><tr><th data-sort>Opened</th><th>Facility</th><th>Cashier</th><th>Status</th><th class="text-right">Float</th><th class="text-right" data-sort>Expected cash</th><th class="text-right">Counted</th><th class="text-right" data-sort>Variance</th><th></th></tr></thead>
                <tbody x-ref="body">
                @forelse ($items as $c)
                    @php $v = $c['variance'] ?? null; $short = $v !== null && \App\Support\Money::cmp($v, '0') !== 0; @endphp
                    <tr data-row><td data-sort="{{ $c['openedAt'] ?? '' }}"><x-time :at="$c['openedAt'] ?? null" /></td><td>{{ $facilityNames[$c['facilityId'] ?? ''] ?? '' }}</td><td>{{ $staffNames[$c['staffId'] ?? ''] ?? \App\Services\Portal\Directory::short($c['staffId'] ?? null) }}</td><td><x-badge :status="$c['status'] ?? 'UNKNOWN'" /></td>
                        <td class="text-right"><x-money :value="$c['openingFloat'] ?? '0'" /></td><td class="text-right" data-sort="{{ $c['expectedCash'] ?? 0 }}"><x-money :value="$c['expectedCash'] ?? '0'" /></td>
                        <td class="text-right">@if (($c['countedCash'] ?? null) !== null)<x-money :value="$c['countedCash']" />@else &mdash; @endif</td>
                        <td class="text-right {{ $short ? 'font-semibold text-red-800' : '' }}" data-sort="{{ $v ?? 0 }}">@if ($v !== null)<x-money :value="$v" />@else &mdash; @endif</td>
                        <td>@if (! empty($c['id']))<a class="underline" href="{{ route('reports.shift', $c['id']) }}">Shift report</a>@endif</td></tr>
                @empty<tr><td colspan="9"><x-empty title="No cash sessions in this period" text="A session opens when a cashier starts a shift on a POS or tablet." icon="cash" /></td></tr>@endforelse
                </tbody></table></div>
            <x-pagination :count="count($sessions->items())" :next="$sessions->next()" />
        @endif
    </x-card>
</x-layouts.app>
