@props(['param' => 'status', 'options' => [], 'current' => '', 'counts' => []])
{{-- Status filter chips: every choice is a visible link (server-side filter), the current one is filled. --}}
<div class="flex flex-wrap items-center gap-2" role="group" aria-label="Filter by status" data-testid="status-chips">
    @foreach ($options as $value => $label)
        @php $on = (string) $current === (string) $value; @endphp
        <a href="{{ request()->fullUrlWithQuery([$param => $value === '' ? null : $value, 'cursor' => null]) }}" @if ($on) aria-current="true" @endif
           class="inline-flex min-h-9 items-center gap-1.5 rounded-full border px-3.5 text-sm font-medium transition-colors {{ $on ? 'border-brand-600 bg-brand-600 text-white' : 'border-stone-300 bg-white text-stone-700 hover:bg-stone-50' }}" data-chip="{{ $value }}">
            {{ $label }}@if (isset($counts[$value]))<span class="rounded-full px-1.5 text-xs {{ $on ? 'bg-white/25' : 'bg-stone-100 text-stone-600' }}">{{ $counts[$value] }}</span>@endif
        </a>
    @endforeach
</div>
