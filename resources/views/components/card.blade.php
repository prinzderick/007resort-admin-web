@props(['title' => null, 'flush' => false])
<section {{ $attributes->merge(['class' => 'mb-5 rounded-xl border border-stone-200 bg-white shadow-sm']) }}>
    @if ($title)
        <div class="flex items-center justify-between gap-2 border-b border-stone-100 px-4 py-3">
            <h2 class="text-sm font-semibold uppercase tracking-wide text-stone-600">{{ $title }}</h2>
            @if (isset($aside))<div class="text-sm">{{ $aside }}</div>@endif
        </div>
    @endif
    <div class="{{ $flush ? '' : 'p-4' }}">{{ $slot }}</div>
</section>
