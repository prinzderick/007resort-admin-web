@props(['label' => 'Save changes'])
{{-- Kept for older pages: the same sticky bar as x-form.actions (dirty count, double-submit guard, Discard). Prefer <x-form.actions>. --}}
<x-form.actions :submit="$label" {{ $attributes }}>{{ $slot }}</x-form.actions>
