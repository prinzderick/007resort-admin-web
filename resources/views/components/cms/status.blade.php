@props(['item' => null, 'status' => null])
@php $word = $status ?? \App\Support\Cms\Cms::status((array) $item); @endphp
<x-status-pill :status="$word" data-cms-status="{{ $word }}">{{ \App\Support\Cms\Cms::statusLabel($word) }}</x-status-pill>
