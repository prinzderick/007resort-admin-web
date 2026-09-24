@props(['tabs', 'current', 'param' => 'tab'])
{{-- $tabs: key => label. Each tab is a link (server-rendered), so it is bookmarkable and works without JavaScript. --}}
<nav class="mb-5 flex flex-wrap gap-1 border-b border-stone-200 text-sm" role="tablist" data-component="tabs">
    @foreach ($tabs as $k => $label)
        <a role="tab" href="{{ request()->fullUrlWithQuery([$param => $k]) }}" class="-mb-px min-h-11 border-b-2 px-3 py-3 {{ $current === $k ? 'border-brand-600 font-semibold text-brand-700' : 'border-transparent text-stone-600 hover:text-stone-900' }}" @if ($current === $k) aria-selected="true" aria-current="page" @endif>{{ $label }}</a>
    @endforeach
</nav>
