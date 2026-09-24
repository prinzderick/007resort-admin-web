@props(['title', 'subtitle' => null, 'crumbs' => []])
<div class="mb-6 flex flex-wrap items-end justify-between gap-3">
    <div>
        @if ($crumbs !== [])
            <nav class="mb-1 flex flex-wrap items-center gap-1 text-xs text-stone-500" aria-label="Breadcrumb">
                @foreach ($crumbs as $label => $href)
                    @if ($href)<a class="hover:underline" href="{{ $href }}">{{ $label }}</a>@else<span>{{ $label }}</span>@endif
                    @unless ($loop->last)<span>/</span>@endunless
                @endforeach
            </nav>
        @endif
        <h1 class="text-2xl font-semibold tracking-tight text-stone-900">{{ $title }}</h1>
        @if ($subtitle)<p class="mt-1 max-w-3xl text-sm text-stone-600">{{ $subtitle }}</p>@endif
    </div>
    @if (isset($actions) && ! $actions->isEmpty())<div class="flex flex-wrap items-center gap-2">{{ $actions }}</div>@endif
</div>
