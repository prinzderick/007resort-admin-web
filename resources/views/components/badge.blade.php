@props(['tone' => null, 'status' => null, 'dot' => true])
@php
    // Legacy tone names used across the views map onto the semantic ones.
    $tone = match ($tone) { 'good' => 'success', 'warn' => 'warning', 'bad' => 'danger', 'default' => 'neutral', null => \App\Support\Status::tone($status), default => $tone };
    $c = \App\Support\Status::classes($tone);
@endphp
<span {{ $attributes->merge(['class' => "inline-flex items-center gap-1.5 whitespace-nowrap rounded-full px-2.5 py-0.5 text-xs font-semibold ring-1 ring-inset {$c['pill']}", 'data-tone' => $tone]) }}>@if ($dot)<span class="size-1.5 rounded-full {{ $c['dot'] }}" aria-hidden="true"></span>@endif{{ $slot->isEmpty() ? str_replace('_', ' ', (string) $status) : $slot }}</span>
