@props(['label', 'value', 'hint' => null, 'tone' => 'default'])
@php
    $ring = ['default' => 'border-stone-200', 'warn' => 'border-amber-300 bg-amber-50', 'bad' => 'border-red-300 bg-red-50', 'good' => 'border-emerald-300 bg-emerald-50'][$tone] ?? 'border-stone-200';
@endphp
<div class="rounded-xl border {{ $ring }} bg-white px-4 py-3 shadow-sm">
    <div class="text-xs font-medium uppercase tracking-wide text-stone-500">{{ $label }}</div>
    <div class="mt-1 text-2xl font-semibold tabular-nums">{{ $value }}</div>
    @if ($hint)<div class="mt-0.5 text-xs text-stone-500">{{ $hint }}</div>@endif
</div>
