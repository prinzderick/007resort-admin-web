<div wire:poll.30s>
    <x-page-header title="Dashboard" subtitle="Property performance for the selected day, with the state of the site and how fresh the data is.">
        <x-slot:actions>
            <label class="flex items-center gap-2 text-sm">Day
                <input type="date" wire:model.live="date" max="{{ \App\Support\Time::today() }}" class="min-h-11 rounded-lg border border-stone-300 px-3 text-sm">
            </label>
        </x-slot:actions>
    </x-page-header>

    <x-freshness :freshness="$d['freshness']" />

    @php
        $stale = ! $d['freshness']->isLive();
        $site = $d['siteStatus'];
    @endphp

    {{-- Site status --}}
    <x-card title="Site status">
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4" data-testid="site-status">
            <div>
                <div class="text-xs uppercase tracking-wide text-stone-500">Status</div>
                <div class="mt-1"><x-badge :status="$site['health']">{{ $site['health'] }}</x-badge></div>
            </div>
            <div>
                <div class="text-xs uppercase tracking-wide text-stone-500">Last heartbeat</div>
                <div class="mt-1 text-sm">@if ($site['lastHeartbeatAt']) <x-time :at="$site['lastHeartbeatAt']" /> ({{ \App\Support\Time::ago($site['lastHeartbeatAt']) }}) @else <span class="text-stone-500">not reported to this account</span> @endif</div>
            </div>
            <div>
                <div class="text-xs uppercase tracking-wide text-stone-500">Last successful sync</div>
                <div class="mt-1 text-sm">@if ($site['lastSyncAt']) <x-time :at="$site['lastSyncAt']" /> ({{ \App\Support\Time::ago($site['lastSyncAt']) }}) @else <span class="text-stone-500">unknown</span> @endif</div>
            </div>
            <div>
                <div class="text-xs uppercase tracking-wide text-stone-500">Outbox queue</div>
                <div class="mt-1 text-sm">
                    @if ($site['detail'])
                        {{ $site['detail']['outbox']['queued'] }} queued &middot; {{ $site['detail']['outbox']['failed'] }} failed &middot; {{ $site['detail']['outbox']['conflict'] }} conflict
                    @else <span class="text-stone-500">visible to IT only</span> @endif
                </div>
            </div>
        </div>
        <x-fetch :of="$d['health']" what="System health" class="mt-3" />
    </x-card>

    {{-- Headline figures: never presented as live when stale --}}
    @if ($d['revenue'] !== [])
        <div class="mb-1 flex items-center gap-2 text-xs text-stone-500">
            @if ($stale) <x-badge tone="warn">{{ $d['freshness']->level === 'offline' ? 'As of last sync' : 'May be out of date' }}</x-badge> @else <x-badge tone="good">Live</x-badge> @endif
            Figures for {{ $d['date'] }}
        </div>
        <div class="mb-5 grid grid-cols-2 gap-3 lg:grid-cols-5" data-testid="headline">
            <x-stat label="Net sales" :value="\App\Support\Money::format($d['totals']['net'])" :tone="$stale ? 'warn' : 'default'" />
            <x-stat label="Orders" :value="$d['totals']['orders']" :tone="$stale ? 'warn' : 'default'" />
            <x-stat label="Refunds" :value="\App\Support\Money::format($d['totals']['refunds'])" :tone="$stale ? 'warn' : 'default'" />
            <x-stat label="Voids" :value="$d['totals']['voids']" :tone="$stale ? 'warn' : 'default'" />
            <x-stat label="Tickets redeemed" :value="$d['totals']['ticketsRedeemed']" :tone="$stale ? 'warn' : 'default'" />
        </div>
    @else
        <x-fetch :of="$d['reportsFetch']" what="Revenue and sales" />
        @if ($d['reportsFetch']->ok()) <x-card><p class="text-sm text-stone-600">No facility figures were returned for this day.</p></x-card> @endif
    @endif

    <div class="grid gap-5 lg:grid-cols-2">
        <x-card title="Revenue by facility" flush>
            @if ($d['revenue'] === [])
                <p class="p-4 text-sm text-stone-500">No data.</p>
            @else
                <div class="overflow-x-auto"><table class="data-table">
                    <thead><tr><th>Facility</th><th class="text-right">Orders</th><th class="text-right">Net sales</th></tr></thead>
                    <tbody>
                    @foreach ($d['revenue'] as $r)
                        <tr><td><a class="underline" href="{{ route('reports.facility', ['facility' => $r['facilityId'], 'date' => $d['date']]) }}">{{ $r['facility'] }}</a></td><td class="text-right tabular-nums">{{ $r['orders'] }}</td><td class="text-right"><x-money :value="$r['net']" /></td></tr>
                    @endforeach
                    </tbody>
                </table></div>
            @endif
        </x-card>

        <x-card title="Payments by method" flush>
            @if ($d['byMethod'] === [])
                <p class="p-4 text-sm text-stone-500">No data.</p>
            @else
                <div class="overflow-x-auto"><table class="data-table">
                    <thead><tr><th>Method</th><th class="text-right">Payments</th><th class="text-right">Amount</th></tr></thead>
                    <tbody>@foreach ($d['byMethod'] as $m => $v)<tr><td>{{ str_replace('_', ' ', $m) }}</td><td class="text-right tabular-nums">{{ $v['count'] }}</td><td class="text-right"><x-money :value="$v['amount']" /></td></tr>@endforeach</tbody>
                </table></div>
            @endif
        </x-card>

        <x-card title="Orders (latest 100)">
            <x-fetch :of="$d['orders']" what="Orders" />
            @if ($d['orders']->ok())
                <div class="flex flex-wrap gap-2">@forelse ($d['orderCounts'] as $s => $n)<span class="rounded-lg border border-stone-200 px-3 py-2 text-sm"><x-badge :status="$s">{{ str_replace('_', ' ', $s) }}</x-badge> <b class="ml-1 tabular-nums">{{ $n }}</b></span>@empty<span class="text-sm text-stone-500">No orders.</span>@endforelse</div>
            @endif
        </x-card>

        <x-card title="Bookings & tickets">
            <x-fetch :of="$d['bookings']" what="Bookings" />
            @if ($d['bookings']->ok())
                <div class="flex flex-wrap gap-2">@forelse ($d['bookingCounts'] as $s => $n)<span class="rounded-lg border border-stone-200 px-3 py-2 text-sm"><x-badge :status="$s">{{ str_replace('_', ' ', $s) }}</x-badge> <b class="ml-1 tabular-nums">{{ $n }}</b></span>@empty<span class="text-sm text-stone-500">No bookings.</span>@endforelse</div>
            @endif
            <p class="mt-3 text-sm text-stone-600">Tickets redeemed today: <b>{{ $d['totals']['ticketsRedeemed'] }}</b> <span class="text-xs text-stone-500">(tickets sold: pending API report)</span></p>
        </x-card>

        <x-card title="Stock indicators">
            <x-fetch :of="$d['stock']" what="Stock" />
            @if ($d['stock']->ok())
                @if ($d['lowStock'] === [])
                    <p class="text-sm text-emerald-800">All tracked items are above their reorder level.</p>
                @else
                    <ul class="divide-y divide-stone-100 text-sm" data-testid="low-stock">
                        @foreach ($d['lowStock'] as $l)<li class="flex justify-between py-1.5"><span>{{ $l['name'] }}</span><span class="tabular-nums text-red-800">{{ rtrim(rtrim($l['onHand'], '0'), '.') ?: '0' }} {{ $l['unit'] }} <span class="text-stone-500">/ reorder at {{ $l['reorderLevel'] }}</span></span></li>@endforeach
                    </ul>
                @endif
                <a class="mt-2 inline-block text-sm underline" href="{{ route('inventory.index') }}">Open inventory</a>
            @endif
        </x-card>

        <x-card title="Attendance today">
            <x-fetch :of="$d['attendance']" what="Attendance" />
            @if ($d['attendance']->ok())
                <div class="flex flex-wrap gap-2">
                    <span class="rounded-lg border border-stone-200 px-3 py-2 text-sm">On duty <b class="ml-1 tabular-nums">{{ $d['attendanceCounts']['OPEN'] ?? 0 }}</b></span>
                    <span class="rounded-lg border border-stone-200 px-3 py-2 text-sm">Clocked out <b class="ml-1 tabular-nums">{{ $d['attendanceCounts']['CLOSED'] ?? 0 }}</b></span>
                    <span class="rounded-lg border border-amber-300 bg-amber-50 px-3 py-2 text-sm">Needs review <b class="ml-1 tabular-nums">{{ $d['attendanceCounts']['NEEDS_REVIEW'] ?? 0 }}</b></span>
                </div>
            @endif
        </x-card>
    </div>

    @if ($d['approvals']->ok())
        <x-card title="Waiting for approval">
            <a href="{{ route('approvals') }}" class="text-sm underline"><b class="tabular-nums">{{ count($d['approvals']->items()) }}</b> request(s) need a decision</a>
        </x-card>
    @endif
</div>
