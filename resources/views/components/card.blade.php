@props(['title' => null, 'subtitle' => null, 'flush' => false])
<section {{ $attributes->merge(['class' => 'mb-5 rounded-xl border border-stone-200 bg-white shadow-sm']) }}>
    @if ($title)
        <div class="flex flex-wrap items-center justify-between gap-2 border-b border-stone-100 px-5 py-4">
            <div><h2 class="text-base font-semibold tracking-tight text-stone-900">{{ $title }}</h2>@if ($subtitle)<p class="mt-0.5 text-xs text-stone-500">{{ $subtitle }}</p>@endif</div>
            @if (isset($aside))<div class="text-sm">{{ $aside }}</div>@endif
        </div>
    @endif
    <div class="{{ $flush ? '' : 'p-5' }}">{{ $slot }}</div>
</section>
