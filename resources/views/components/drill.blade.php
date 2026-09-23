@props(['at'])
@php
    $levels = ['Property', 'Facility', 'Operating point', 'Terminal', 'Staff', 'Transaction'];
    $pending = ['Operating point' => 'per-operating-point totals need an API report'];
@endphp
<nav class="mb-4 flex flex-wrap items-center gap-1 text-xs" aria-label="Drill-down">
    @foreach ($levels as $i => $l)
        <span class="rounded-full px-2.5 py-1 {{ $l === $at ? 'bg-stone-900 text-white' : (isset($pending[$l]) ? 'border border-dashed border-stone-300 text-stone-400' : 'border border-stone-300 text-stone-600') }}"
              @isset($pending[$l]) title="{{ $pending[$l] }}" @endisset>{{ $l }}</span>
        @unless ($loop->last)<span class="text-stone-300">&rsaquo;</span>@endunless
    @endforeach
</nav>
