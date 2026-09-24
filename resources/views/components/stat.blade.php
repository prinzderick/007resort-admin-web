@props(['label', 'value', 'hint' => null, 'tone' => 'default', 'delta' => null, 'deltaLabel' => 'vs previous period', 'invert' => false, 'spark' => null])
@php
    $ring = ['default' => 'border-stone-200', 'warn' => 'border-amber-300 bg-amber-50/50', 'bad' => 'border-red-300 bg-red-50/50', 'good' => 'border-brand-200 bg-brand-50/50'][$tone] ?? 'border-stone-200';
    $up = $delta !== null && $delta > 0; $down = $delta !== null && $delta < 0;
    $good = $invert ? $down : $up; $bad = $invert ? $up : $down;
    $pts = null;
    if (is_array($spark) && count($spark) > 1) {
        $max = max($spark) ?: 1; $min = min($spark); $span = max(0.0001, $max - $min); $n = count($spark) - 1;
        $pts = implode(' ', array_map(fn ($v, $i) => round($i / $n * 100, 1).','.round(28 - (($v - $min) / $span) * 26, 1), $spark, array_keys($spark)));
    }
@endphp
<div class="rounded-xl border {{ $ring }} bg-white px-5 py-4 shadow-[var(--shadow-card)]" data-component="stat-card">
    <div class="flex items-start justify-between gap-3">
        <div class="min-w-0"><div class="t-label">{{ $label }}</div><div class="mt-1.5 break-words text-2xl font-semibold tabular-nums tracking-tight">{{ $value }}</div></div>
        @if ($pts)<svg viewBox="0 0 100 30" class="h-8 w-20 shrink-0 text-brand-600" preserveAspectRatio="none" aria-hidden="true"><polyline points="{{ $pts }}" fill="none" stroke="currentColor" stroke-width="2" stroke-linejoin="round" stroke-linecap="round" vector-effect="non-scaling-stroke"/></svg>@endif
    </div>
    @if ($delta !== null)
        <div class="mt-1.5 flex items-center gap-1 text-xs {{ $good ? 'text-brand-700' : ($bad ? 'text-red-700' : 'text-stone-500') }}" data-testid="delta"><span aria-hidden="true">{{ $up ? '↑' : ($down ? '↓' : '→') }}</span><span class="font-semibold">{{ $delta > 0 ? '+' : '' }}{{ rtrim(rtrim(number_format($delta, 1), '0'), '.') }}%</span><span class="text-stone-500">{{ $deltaLabel }}</span></div>
    @elseif ($hint)<div class="mt-1.5 text-xs text-stone-500">{{ $hint }}</div>@endif
</div>
