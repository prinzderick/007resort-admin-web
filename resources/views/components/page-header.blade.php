@props(['title', 'subtitle' => null])
<div class="mb-5 flex flex-wrap items-end justify-between gap-3">
    <div>
        <h1 class="text-2xl font-semibold tracking-tight">{{ $title }}</h1>
        @if ($subtitle)<p class="mt-1 text-sm text-stone-600">{{ $subtitle }}</p>@endif
    </div>
    @if (isset($actions) && ! $actions->isEmpty())<div class="flex flex-wrap items-center gap-2">{{ $actions }}</div>@endif
</div>
