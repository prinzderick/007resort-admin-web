@props(['items'])
{{-- $items: list of [label, route, match, permissions[]] --}}
<nav class="mb-5 flex flex-wrap gap-1 border-b border-stone-200 pb-0 text-sm" aria-label="Section">
    @foreach ($items as [$label, $route, $match, $perms])
        @if ($perms === [] || auth_staff()->canAny(...$perms))
            <a href="{{ route($route) }}" class="-mb-px min-h-10 border-b-2 px-3 py-2 {{ \App\Support\Navigation::isActive($match) ? 'border-brand-600 font-semibold text-brand-700' : 'border-transparent text-stone-600 hover:text-stone-900' }}">{{ $label }}</a>
        @endif
    @endforeach
</nav>
