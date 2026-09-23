@props(['items'])
{{-- $items: list of [label, route, match, permissions[]] --}}
<nav class="mb-5 flex flex-wrap gap-1 border-b border-stone-200 pb-2 text-sm" aria-label="Section">
    @foreach ($items as [$label, $route, $match, $perms])
        @if ($perms === [] || auth_staff()->canAny(...$perms))
            <a href="{{ route($route) }}" class="min-h-10 rounded-lg px-3 py-2 {{ request()->routeIs($match) ? 'bg-stone-900 text-white' : 'text-stone-700 hover:bg-stone-200' }}">{{ $label }}</a>
        @endif
    @endforeach
</nav>
