@props(['status' => null, 'tone' => null])
<x-badge :status="$status" :tone="$tone" {{ $attributes }}>{{ $slot }}</x-badge>
