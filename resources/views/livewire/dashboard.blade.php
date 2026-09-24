@php
    use App\Support\Money;
    $range = $d['range'];
    $fresh = $d['freshness'];
    $stale = ! $fresh->isLive();
    $site = $d['siteStatus'];
    $k = $d['kpi'];
    $cmp = 'vs previous '.$range->days().' day'.($range->days() === 1 ? '' : 's');
    $tone = $stale ? 'warn' : 'default';
    $hist = $d['history'];
    $lineCfg = ['type' => 'line', 'data' => ['labels' => $hist['labels'], 'datasets' => [
        ['label' => 'This period', 'data' => $hist['current']],
        ['label' => 'Previous period', 'data' => $hist['previous'], 'borderDash' => [5, 4], 'borderColor' => '#2b6cde', 'backgroundColor' => '#2b6cde'],
    ]], 'options' => ['plugins' => ['legend' => ['position' => 'top', 'align' => 'end']], 'scales' => ['y' => ['beginAtZero' => true, 'grid' => ['color' => '#eef0f3']], 'x' => ['grid' => ['display' => false]]]]];
    $donutCfg = ['type' => 'doughnut', 'data' => ['labels' => array_map(fn ($m) => str_replace('_', ' ', $m['method']), $d['byMethod']), 'datasets' => [['data' => array_map(fn ($m) => (float) $m['captured'], $d['byMethod'])]]], 'options' => ['cutout' => '62%', 'plugins' => ['legend' => ['position' => 'bottom']]]];
@endphp
<div wire:poll.60s>
    <div class="mb-6 flex flex-wrap items-end justify-between gap-3">
        <div><h1 class="text-3xl font-semibold tracking-tight text-brand-900">Dashboard</h1><p class="mt-1 text-sm text-stone-600">{{ $range->label() }} &middot; {{ $range->days() }} day{{ $range->days() === 1 ? '' : 's' }}, compared with the {{ $range->days() }} before.</p></div>
        <div class="flex items-center gap-2 text-xs text-stone-500"><span class="inline-flex items-center gap-1.5 rounded-full border px-2.5 py-1 font-semibold {{ $stale ? 'border-amber-300 bg-amber-50 text-amber-900' : 'border-brand-200 bg-brand-50 text-brand-800' }}" data-testid="freshness-banner" data-level="{{ $fresh->level }}">{{ $stale ? ($fresh->level === 'offline' ? 'As of last sync' : 'May be out of date') : 'Live' }}</span><span>{{ $fresh->message }}</span></div>
    </div>

    <div class="grid gap-5 xl:grid-cols-[minmax(0,1fr)_22rem]">
        <div class="min-w-0">
            {{-- KPI cards --}}
            @if ($d['hasRevenue'])
                <div class="mb-5 grid grid-cols-2 gap-4 lg:grid-cols-3 2xl:grid-cols-6" data-testid="headline">
                    <x-stat label="Net sales" :value="Money::format($k['sales']['now'])" :delta="$k['sales']['delta']" :delta-label="$cmp" :tone="$tone" />
                    <x-stat label="Transactions" :value="number_format($k['payments']['now'])" :delta="$k['payments']['delta']" :delta-label="$cmp" :tone="$tone" />
                    <x-stat label="Orders" :value="number_format($k['orders']['now'])" :delta="$k['orders']['delta']" :delta-label="$cmp" :tone="$tone" />
                    <x-stat label="Average order" :value="Money::format($k['avg']['now'])" :delta="$k['avg']['delta']" :delta-label="$cmp" :tone="$tone" />
                    <x-stat label="Refunds" :value="Money::format($k['refunds']['now'])" :delta="$k['refunds']['delta']" :delta-label="$cmp" :invert="true" :tone="$tone" />
                    <x-stat :label="'Voids / tickets on '.\Carbon\CarbonImmutable::parse($range->to)->format('M j')" :value="$d['day']['voids'].' / '.$d['day']['tickets']" hint="voided orders / tickets redeemed" :tone="$tone" />
                </div>
            @else
                <x-fetch :of="$d['reportsFetch']" what="Revenue and sales" />
            @endif

            {{-- Revenue history --}}
            <x-card title="Revenue history" subtitle="Order revenue per day, this period against the previous one">
                @if ($d['hasRevenue'] && $hist['labels'] !== [])
                    <div class="h-72" x-data="chart(@js($lineCfg))" wire:key="line-{{ md5(json_encode($hist)) }}"><canvas x-ref="canvas" aria-label="Revenue history chart" role="img"></canvas></div>
                @elseif ($d['hasRevenue'])
                    <x-empty title="No history to draw" text="The line needs a period of up to 31 days. Choose a shorter range with the date picker." icon="chart" />
                @else
                    <x-empty title="Revenue is not available to this account" text="Ask an administrator for the report.view.all permission." icon="lock" />
                @endif
            </x-card>

            <div class="grid gap-5 lg:grid-cols-2">
                <x-card title="Payments by channel" subtitle="Captured amount by payment method">
                    @if ($d['byMethod'] === [])
                        <x-empty title="No payments in this period" icon="card" />
                    @else
                        <div class="h-64" x-data="chart(@js($donutCfg))" wire:key="donut-{{ md5(json_encode($d['byMethod'])) }}"><canvas x-ref="canvas" aria-label="Payments by channel chart" role="img"></canvas></div>
                        <div class="overflow-x-auto"><table class="data-table mt-3" data-testid="by-method"><thead><tr><th>Method</th><th class="text-right">Payments</th><th class="text-right">Captured</th><th class="text-right">Net of refunds</th></tr></thead><tbody>
                            @foreach ($d['byMethod'] as $m)<tr><td>{{ str_replace('_', ' ', $m['method']) }}</td><td class="text-right tabular-nums">{{ $m['count'] }}</td><td class="text-right"><x-money :value="$m['captured']" /></td><td class="text-right"><x-money :value="$m['net']" /></td></tr>@endforeach
                        </tbody></table></div>
                    @endif
                </x-card>

                <x-card title="Transaction success rate" subtitle="Share of payments that went through">
                    <x-fetch :of="$d['payments']" what="Payments" />
                    @if ($d['success'])
                        @php $sr = $d['success']; @endphp
                        <div class="flex items-center gap-4"><span class="text-3xl font-semibold tabular-nums" data-testid="success-rate">{{ rtrim(rtrim(number_format($sr['rate'], 1), '0'), '.') }}%</span>
                            <div class="h-4 flex-1 overflow-hidden rounded bg-stone-100"><div class="h-full bg-brand-500" style="width: {{ $sr['rate'] }}%"></div></div></div>
                        @if ($sr['best'])<p class="mt-3 rounded-lg bg-stone-50 p-3 text-sm"><b>{{ str_replace('_', ' ', $sr['best']['method']) }}</b> has the highest success rate at <b>{{ rtrim(rtrim(number_format($sr['best']['rate'], 1), '0'), '.') }}%</b>.</p>@endif
                        <table class="data-table mt-3"><tbody>@foreach ($sr['byMethod'] as $m)<tr><td>{{ str_replace('_', ' ', $m['method']) }}</td><td class="text-right tabular-nums text-stone-500">{{ $m['total'] }} payments</td><td class="text-right font-medium tabular-nums">{{ rtrim(rtrim(number_format($m['rate'], 1), '0'), '.') }}%</td></tr>@endforeach</tbody></table>
                        @if ($d['paymentsCapped'] ?? false)<p class="mt-2 text-xs text-stone-500">Based on the latest 200 payments in the period.</p>@endif
                    @elseif ($d['payments']->ok())
                        <x-empty title="No payments in this period" icon="card" />
                    @endif
                </x-card>
            </div>

            {{-- Facility performance --}}
            <x-card title="Facility performance" subtitle="Order revenue by facility for the period" flush>
                @if ($d['byFacility'] === [])
                    <x-empty title="No sales in this period" text="Facilities appear here as soon as they take orders." icon="building" />
                @else
                    <div class="overflow-x-auto"><table class="data-table" data-testid="facility-table"><thead><tr><th>Facility</th><th class="text-right">Orders</th><th class="text-right">Revenue</th><th class="text-right">Avg order</th><th class="w-40">Share</th></tr></thead><tbody>
                        @foreach ($d['byFacility'] as $f)
                            <tr><td>@if ($f['id'])<a class="font-medium text-brand-700 underline decoration-brand-200" href="{{ route('reports.facility', ['facility' => $f['id'], 'date' => $range->to]) }}">{{ $f['name'] }}</a>@else{{ $f['name'] }}@endif</td>
                                <td class="text-right tabular-nums">{{ $f['orders'] }}</td><td class="text-right"><x-money :value="$f['revenue']" /></td><td class="text-right"><x-money :value="$f['orders'] > 0 ? bcdiv($f['revenue'], (string) $f['orders'], 4) : '0'" /></td>
                                <td><div class="flex items-center gap-2"><div class="h-2 flex-1 overflow-hidden rounded bg-stone-100"><div class="h-full bg-brand-500" style="width: {{ min(100, $f['share']) }}%"></div></div><span class="w-10 text-right text-xs tabular-nums">{{ $f['share'] }}%</span></div></td></tr>
                        @endforeach
                    </tbody></table></div>
                @endif
            </x-card>

            {{-- Operations snapshot --}}
            <div class="grid gap-5 lg:grid-cols-3">
                <x-card title="Orders (latest 100)">
                    <x-fetch :of="$d['orders']" what="Orders" />
                    @if ($d['orders']->ok())<div class="flex flex-wrap gap-2">@forelse ($d['orderCounts'] as $s => $n)<a href="{{ route('orders.index', ['status' => $s]) }}" class="rounded-lg border border-stone-200 px-3 py-2 text-sm hover:bg-stone-50"><x-badge :status="$s">{{ str_replace('_', ' ', $s) }}</x-badge> <b class="ml-1 tabular-nums">{{ $n }}</b></a>@empty<span class="text-sm text-stone-500">No orders.</span>@endforelse</div>@endif
                </x-card>
                <x-card title="Bookings">
                    <x-fetch :of="$d['bookings']" what="Bookings" />
                    @if ($d['bookings']->ok())<div class="flex flex-wrap gap-2">@forelse ($d['bookingCounts'] as $s => $n)<a href="{{ route('bookings.index', ['status' => $s]) }}" class="rounded-lg border border-stone-200 px-3 py-2 text-sm hover:bg-stone-50"><x-badge :status="$s">{{ str_replace('_', ' ', $s) }}</x-badge> <b class="ml-1 tabular-nums">{{ $n }}</b></a>@empty<span class="text-sm text-stone-500">No bookings.</span>@endforelse</div>@endif
                </x-card>
                <x-card title="Attendance on {{ \Carbon\CarbonImmutable::parse($range->to)->format('M j') }}">
                    <x-fetch :of="$d['attendance']" what="Attendance" />
                    @if ($d['attendance']->ok())
                        <div class="flex flex-wrap gap-2 text-sm">
                            <span class="rounded-lg border border-stone-200 px-3 py-2">On duty <b class="ml-1 tabular-nums">{{ $d['attendanceCounts']['OPEN'] ?? 0 }}</b></span>
                            <span class="rounded-lg border border-stone-200 px-3 py-2">Clocked out <b class="ml-1 tabular-nums">{{ $d['attendanceCounts']['CLOSED'] ?? 0 }}</b></span>
                            <span class="rounded-lg border border-amber-300 bg-amber-50 px-3 py-2">Needs review <b class="ml-1 tabular-nums">{{ $d['attendanceCounts']['NEEDS_REVIEW'] ?? 0 }}</b></span>
                        </div>
                    @endif
                </x-card>
            </div>
        </div>

        {{-- Right rail --}}
        <div class="min-w-0">
            <x-card title="Site status" data-testid="site-status">
                <div class="flex items-center justify-between"><x-badge :status="$site['health']">{{ $site['health'] }}</x-badge><a class="text-xs font-medium text-brand-700 underline" href="{{ route('sync') }}">Sync &amp; IT</a></div>
                <dl class="mt-3 space-y-2 text-sm">
                    <div class="flex justify-between gap-3"><dt class="text-stone-500">Last successful sync</dt><dd>@if ($site['lastSyncAt']) <x-time :at="$site['lastSyncAt']" ago /> @else <span class="text-stone-500">unknown</span> @endif</dd></div>
                    <div class="flex justify-between gap-3"><dt class="text-stone-500">Outbox queue</dt><dd>@if ($site['detail']){{ $site['detail']['outbox']['queued'] ?? 0 }} queued &middot; {{ $site['detail']['outbox']['failed'] ?? 0 }} failed @else <span class="text-stone-500">visible to IT only</span> @endif</dd></div>
                </dl>
                <x-fetch :of="$d['health']" what="System health" class="mt-3" />
            </x-card>

            <x-card title="Needs attention" flush data-testid="alerts">
                @forelse ($d['alerts'] as $a)
                    <a href="{{ route($a['route']) }}" class="flex items-start gap-3 border-b border-stone-100 px-5 py-3 text-sm last:border-0 hover:bg-stone-50"><span class="mt-1.5 size-2 shrink-0 rounded-full {{ $a['tone'] === 'bad' ? 'bg-red-600' : 'bg-amber-500' }}"></span><span>{{ $a['text'] }}</span></a>
                @empty
                    <p class="px-5 py-6 text-center text-sm text-stone-500">All clear. Nothing needs attention.</p>
                @endforelse
            </x-card>

            @if ($onboarding && ($onboarding['percent'] ?? 100) < 100)
                <x-card title="Finish setting up" :subtitle="($onboarding['percent'] ?? 0).'% complete'" data-testid="setup-progress">
                    <div class="mb-3 h-2 overflow-hidden rounded-full bg-stone-100"><div class="h-full rounded-full bg-brand-600" style="width: {{ $onboarding['percent'] ?? 0 }}%"></div></div>
                    <ul class="space-y-2 text-sm">@foreach (array_slice(array_values(array_filter($onboarding['steps'] ?? [], fn ($s) => ! ($s['done'] ?? false))), 0, 4) as $st)<li><a class="flex items-start gap-2 hover:underline" href="{{ route($st['route'] ?? 'setup.index') }}"><span class="mt-1 size-3 shrink-0 rounded-full border-2 border-stone-300"></span>{{ $st['label'] ?? '' }}</a></li>@endforeach</ul>
                    <a href="{{ route('setup.index') }}" class="mt-3 inline-block text-xs font-medium text-brand-700 underline">Open Setup</a>
                </x-card>
            @endif

            <x-card title="Recent transactions" flush>
                <div class="flex items-center justify-between border-b border-stone-100 px-5 py-2 text-xs"><span class="text-stone-500">Latest payments</span>@if (auth_staff()->can('payment.view'))<a class="font-medium text-brand-700 underline" href="{{ route('finance.payments') }}">View all</a>@endif</div>
                @forelse ($d['recent'] as $p)
                    <a href="{{ ! empty($p['id']) ? route('finance.payment', $p['id']) : '#' }}" class="flex items-center justify-between gap-3 border-b border-stone-100 px-5 py-3 text-sm last:border-0 hover:bg-stone-50">
                        <span><span class="block font-medium">{{ str_replace('_', ' ', $p['tenderType'] ?? '') }}</span><span class="block text-xs text-stone-500"><x-badge :status="$p['status'] ?? 'UNKNOWN'" /> &middot; <x-time :at="$p['createdAt'] ?? null" /></span></span>
                        <span class="font-semibold tabular-nums text-brand-700"><x-money :value="$p['amount'] ?? '0'" /></span></a>
                @empty
                    <p class="px-5 py-6 text-center text-sm text-stone-500">No payments in this period.</p>
                @endforelse
            </x-card>

            @if ($d['heatmap'])
                @php $h = $d['heatmap']; @endphp
                <x-card title="Transactions by time" subtitle="Payments by weekday and time of day" data-testid="heatmap">
                    <div class="grid grid-cols-[3.2rem_repeat(7,minmax(0,1fr))] gap-1 text-[10px] text-stone-500">
                        <span></span>@foreach ($h['days'] as $day)<span class="text-center">{{ $day }}</span>@endforeach
                        @foreach ($h['blocks'] as $bi => $label)
                            <span class="self-center text-right pr-1">{{ $label }}</span>
                            @foreach ($h['cells'][$bi] as $n)
                                @php $a = $n === 0 ? 0 : 0.18 + 0.82 * ($n / $h['max']); @endphp
                                <span class="flex h-6 items-center justify-center rounded text-[10px] {{ $a > 0.55 ? 'text-white' : 'text-stone-700' }}" style="background: rgba(15, 125, 79, {{ $n === 0 ? 0.06 : round($a, 2) }})" title="{{ $n }} payment(s)">{{ $n ?: '' }}</span>
                            @endforeach
                        @endforeach
                    </div>
                    @if ($d['paymentsCapped'] ?? false)<p class="mt-2 text-xs text-stone-500">Latest 200 payments in the period.</p>@endif
                </x-card>
            @endif

            <x-card title="Stock alerts" data-testid="low-stock">
                <x-fetch :of="$d['stock']" what="Stock" />
                @if ($d['stock']->ok())
                    @if ($d['lowStock'] === [])<p class="text-sm text-brand-800">All tracked items are above their reorder level.</p>
                    @else<ul class="divide-y divide-stone-100 text-sm">@foreach (array_slice($d['lowStock'], 0, 6) as $l)<li class="flex justify-between gap-3 py-1.5"><span class="truncate">{{ $l['name'] }}</span><span class="shrink-0 tabular-nums text-red-800">{{ str_contains($l['onHand'], '.') ? (rtrim(rtrim($l['onHand'], '0'), '.') ?: '0') : $l['onHand'] }} {{ $l['unit'] }} <span class="text-stone-500">/ {{ rtrim(rtrim((string) $l['reorderLevel'], '0'), '.') }}</span></span></li>@endforeach</ul>
                        @if (count($d['lowStock']) > 6)<a class="mt-2 inline-block text-xs font-medium text-brand-700 underline" href="{{ route('inventory.index') }}">+{{ count($d['lowStock']) - 6 }} more</a>@endif
                    @endif
                @endif
            </x-card>
        </div>
    </div>
</div>
