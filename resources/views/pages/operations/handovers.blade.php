<x-layouts.app title="Tab hand-overs">
    <x-page-header title="Tab hand-overs" subtitle="Waiters handing their open tabs to a colleague, who must accept on their tablet. Reassignments by a supervisor are listed too." :crumbs="['Open tabs' => route('waiter-flow.open-tabs'), 'Hand-overs' => null]" />
    <x-filter-form :reset="route('waiter-flow.handovers')">
        <x-filter-select name="facilityId" label="Facility" :options="collect($facilities)->pluck('name', 'id')->all()" :value="$facilityId" all="" />
        <x-filter-select name="status" label="Status" :options="$statuses" :value="$status" all="All" />
    </x-filter-form>
    @if ($list === null)
        <x-empty title="No table-service facility" text="Nothing to show yet." icon="repeat" />
    @else
        <x-card flush>
            <x-fetch :of="$list" what="Hand-overs" />
            @if ($list->ok())
                <div class="table-scroll"><table class="data-table" data-testid="handovers-table">
                    <thead><tr><th>When</th><th>From</th><th>To</th><th>Orders</th><th class="num">Owed</th><th>Status</th><th>Note</th></tr></thead>
                    <tbody>
                    @forelse ($list->items() as $h)
                        @php $st = $h['status'] ?? ''; @endphp
                        <tr>
                            <td class="whitespace-nowrap"><x-time :at="$h['createdAt'] ?? null" /></td>
                            <td class="font-medium">{{ $h['fromName'] ?? '-' }}</td>
                            <td class="font-medium">{{ $h['toName'] ?? '-' }}</td>
                            <td class="text-xs text-stone-600">{{ collect($h['items'] ?? [])->map(fn ($i) => $i['number'].($i['tableLabel'] ? ' ('.$i['tableLabel'].')' : ''))->implode(', ') }}</td>
                            <td class="num"><x-money :value="$h['balance'] ?? '0'" /></td>
                            <td><x-badge :status="$st" tone="{{ $st === 'ACCEPTED' ? 'good' : ($st === 'PENDING' ? 'warn' : ($st === 'FORCED' ? 'info' : 'neutral')) }}">{{ $statuses[$st] ?? $st }}</x-badge></td>
                            <td class="max-w-56 truncate text-xs text-stone-500" title="{{ $h['note'] ?? '' }}">{{ $h['note'] ?? '' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="7"><x-empty title="No hand-overs yet" text="They appear here when a waiter hands tabs to a colleague." icon="repeat" /></td></tr>
                    @endforelse
                    </tbody></table></div>
            @endif
        </x-card>
    @endif
</x-layouts.app>
