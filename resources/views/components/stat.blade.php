@props(['label', 'value', 'hint' => null, 'tone' => 'default', 'delta' => null, 'deltaLabel' => 'vs previous period', 'invert' => false])
@php
    $ring = ['default' => 'border-stone-200', 'warn' => 'border-amber-300 bg-amber-50', 'bad' => 'border-red-300 bg-red-50', 'good' => 'border-brand-200 bg-brand-50'][$tone] ?? 'border-stone-200';
    // $delta: percentage change (float) or null when there is no previous figure to compare with.
    $up = $delta !== null && $delta > 0; $down = $delta !== null && $delta < 0;
    $good = $invert ? $down : $up; $bad = $invert ? $up : $down;
@endphp
<div class="rounded-xl border {{ $ring }} bg-white px-5 py-4 shadow-sm">
    <div class="text-xs font-medium uppercase tracking-wide text-stone-500">{{ $label }}</div>
    <div class="mt-1.5 text-2xl font-semibold tabular-nums tracking-tight">{{ $value }}</div>
    @if ($delta !== null)
        <div class="mt-1 flex items-center gap-1 text-xs {{ $good ? 'text-brand-700' : ($bad ? 'text-red-700' : 'text-stone-500') }}" data-testid="delta">
            <span aria-hidden="true">{{ $up ? '↑' : ($down ? '↓' : '→') }}</span><span class="font-semibold">{{ $delta > 0 ? '+' : '' }}{{ rtrim(rtrim(number_format($delta, 1), '0'), '.') }}%</span><span class="text-stone-500">{{ $deltaLabel }}</span>
        </div>
    @elseif ($hint)<div class="mt-1 text-xs text-stone-500">{{ $hint }}</div>@endif
</div>
