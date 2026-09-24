<ul class="{{ ($depth ?? 0) > 0 ? 'ml-5 border-l border-stone-200 pl-3' : '' }} text-sm">
    @foreach ($nodes as $n)
        <li class="py-1">
            <a class="underline {{ ($selected ?? null) === $n['id'] ? 'font-semibold' : '' }}" href="{{ route('setup.facilities', ['facility' => $n['id']]) }}">{{ $n['name'] }}</a>
            <span class="text-xs text-stone-500">{{ $n['kind'] }} &middot; {{ $n['status'] }}</span>
            @if (! empty($n['children']))@include('partials.facility-tree', ['nodes' => $n['children'], 'depth' => ($depth ?? 0) + 1, 'selected' => $selected ?? null])@endif
        </li>
    @endforeach
</ul>
