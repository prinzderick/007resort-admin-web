@props(['reset' => null])
{{-- Filter bar: a GET form whose controls line up on one baseline. Put x-filter-* controls inside. --}}
<form method="GET" {{ $attributes->class(['mb-4 flex flex-wrap items-end gap-3']) }} data-component="filters">
    {{ $slot }}
    <div class="flex items-end gap-2"><button class="f-btn" data-variant="secondary" type="submit">Apply</button>@if ($reset)<a href="{{ $reset }}" class="inline-flex min-h-10 items-center px-2 text-sm font-medium text-brand-700 underline">Clear</a>@endif</div>
</form>
