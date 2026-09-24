@props(['tone' => 'info', 'title' => null])
@php
    $c = ['info' => 'border-sky-200 bg-sky-50 text-sky-900', 'success' => 'border-brand-200 bg-brand-50 text-brand-900', 'warning' => 'border-amber-300 bg-amber-50 text-amber-950', 'danger' => 'border-red-300 bg-red-50 text-red-900'][$tone] ?? 'border-sky-200 bg-sky-50 text-sky-900';
    $icon = ['info' => 'info', 'success' => 'check', 'warning' => 'alert', 'danger' => 'alert'][$tone] ?? 'info';
@endphp
<div {{ $attributes->merge(['class' => "mb-4 flex gap-3 rounded-xl border px-4 py-3 text-sm {$c}", 'role' => in_array($tone, ['danger', 'warning']) ? 'alert' : 'status', 'data-tone' => $tone]) }}><x-icon :name="$icon" class="mt-0.5 size-4 shrink-0" /><div>@if ($title)<div class="font-semibold">{{ $title }}</div>@endif{{ $slot }}</div></div>
