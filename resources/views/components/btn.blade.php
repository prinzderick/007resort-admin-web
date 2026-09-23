@props(['variant' => 'primary', 'href' => null, 'type' => 'submit'])
@php
    $cls = ['primary' => 'bg-stone-900 text-white hover:bg-stone-700', 'secondary' => 'border border-stone-300 bg-white text-stone-800 hover:bg-stone-50', 'danger' => 'bg-red-700 text-white hover:bg-red-600'][$variant] ?? '';
    $base = "inline-flex min-h-11 items-center justify-center rounded-lg px-4 text-sm font-medium {$cls}";
@endphp
@if ($href)
    <a href="{{ $href }}" {{ $attributes->merge(['class' => $base]) }}>{{ $slot }}</a>
@else
    <button type="{{ $type }}" {{ $attributes->merge(['class' => $base]) }}>{{ $slot }}</button>
@endif
