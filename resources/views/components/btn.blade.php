@props(['variant' => 'primary', 'href' => null, 'type' => 'submit', 'icon' => null])
@php
    $cls = ['primary' => 'bg-brand-600 text-white hover:bg-brand-700', 'secondary' => 'border border-stone-300 bg-white text-stone-800 hover:bg-stone-50', 'danger' => 'bg-red-700 text-white hover:bg-red-600', 'ghost' => 'text-brand-700 hover:bg-brand-50'][$variant] ?? '';
    $base = "inline-flex min-h-10 items-center justify-center gap-2 rounded-lg px-4 text-sm font-medium transition-colors {$cls}";
@endphp
@if ($href)
    <a href="{{ $href }}" {{ $attributes->merge(['class' => $base]) }}>@if ($icon)<x-icon :name="$icon" class="size-4" />@endif{{ $slot }}</a>
@else
    <button type="{{ $type }}" {{ $attributes->merge(['class' => $base]) }}>@if ($icon)<x-icon :name="$icon" class="size-4" />@endif{{ $slot }}</button>
@endif
