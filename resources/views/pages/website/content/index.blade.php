@php
    use App\Support\Cms\Cms;
    $key = $def['key'];
    $filterOpts = fn ($f) => $f['options'] ?? ($options[$f['optionsFrom'] ?? ''] ?? []);
    $cell = function (array $i, string $kind) use ($def) {
        [$type, $arg] = array_pad(explode(':', $kind, 2), 2, null);
        return [$type, $arg];
    };
    $site = $def['sitePath'] ?? null;
    $liveOnSite = fn (array $i) => $site && config('r007.site_url') && in_array(Cms::status($i), ['PUBLISHED'], true);
@endphp
<x-cms.layout :title="$def['plural']">
    <x-page-header :title="$def['plural']" :subtitle="$def['subtitle']" :crumbs="['Website' => route('website.index'), $def['plural'] => null]">
        <x-slot:actions>
            @if ($key === 'posts' || $key === 'categories')
                <x-btn variant="secondary" :href="route($key === 'posts' ? 'website.categories.index' : 'website.posts.index')">{{ $key === 'posts' ? 'Categories' : 'Back to posts' }}</x-btn>
            @endif
            @if ($canManage)<x-btn :href="route('website.'.$key.'.create')" icon="plus" data-testid="add-new">Add {{ strtolower($def['label']) }}</x-btn>@endif
        </x-slot:actions>
    </x-page-header>

    @if ($def['publish'])<div class="mb-4"><x-cms.chips :options="$chips" :current="request('status', '')" :counts="$counts" /></div>@endif

    <x-filter-form :reset="route('website.'.$key.'.index')">
        <x-filter-text name="q" label="Search" :value="request('q')" :placeholder="$def['searchHint']" width="16rem" />
        @foreach ($def['filters'] as $f)
            <x-filter-select :name="$f['name']" :label="$f['label']" :options="$filterOpts($f)" :value="request($f['name'])" :all="$f['all'] ?? 'All'" width="13rem" />
        @endforeach
        @if (request('status'))<input type="hidden" name="status" value="{{ request('status') }}">@endif
    </x-filter-form>

    <x-card flush x-data="tableTools">
        <x-fetch :of="$list" :what="$def['plural']" />
        @if ($list->ok())
            @if ($items === [] && ! request()->hasAny(['q', 'status', ...array_column($def['filters'], 'name')]))
                <x-empty :title="$def['empty']['title']" :text="$def['empty']['text']" icon="file">
                    @if ($canManage)<x-btn class="mt-4" :href="route('website.'.$key.'.create')" icon="plus" data-testid="add-first">{{ $def['empty']['action'] }}</x-btn>@else<p class="mt-3 text-xs text-stone-500">You can view this list but not add to it (needs the cms.manage permission).</p>@endif
                </x-empty>
            @else
                <x-table-tools :placeholder="'Filter these '.strtolower($def['plural']).'...'" :csv="false" :selectable="false" />
                <div class="overflow-x-auto">
                <table class="data-table" data-testid="content-table">
                    <thead><tr>
                        @foreach ($def['columns'] as [$ck, $label, $kind])<th @if (! str_starts_with($kind, 'thumb') && $label !== '') data-sort @endif class="{{ str_starts_with($kind, 'count') ? 'num' : '' }}">{{ $label }}</th>@endforeach
                        <th class="text-right">Actions</th>
                    </tr></thead>
                    <tbody>
                    @forelse ($items as $i)
                        @php $id = $i['id']; $st = Cms::status($i); @endphp
                        <tr data-row data-status="{{ $st }}" data-testid="row">
                            @foreach ($def['columns'] as [$ck, $label, $kind])
                                @php [$type, $arg] = $cell($i, $kind); @endphp
                                @switch($type)
                                    @case('thumb')
                                        @php $th = Cms::thumb(is_array($i[$arg] ?? null) ? $i[$arg] : null, 240); @endphp
                                        <td class="w-16">@if ($th)<img src="{{ $th }}" alt="" class="h-10 w-14 rounded-md object-cover" loading="lazy">@else<span class="flex h-10 w-14 items-center justify-center rounded-md bg-stone-100 text-stone-300"><x-icon name="image" class="size-4" /></span>@endif</td>
                                        @break
                                    @case('title')
                                        <td data-sort="{{ $i[$arg ?? 'title'] ?? '' }}"><a class="font-medium text-brand-700 underline decoration-brand-200 underline-offset-2" href="{{ route('website.'.$key.'.edit', $id) }}">{{ $i[$arg ?? 'title'] ?? '(untitled)' }}</a>@if (! empty($i['slug']))<div class="text-xs text-stone-500">/{{ $i['slug'] }}</div>@endif @if (($i['featured'] ?? false) && $key !== 'events') <span class="sr-only">featured</span>@endif</td>
                                        @break
                                    @case('status')
                                        <td data-sort="{{ $st }}"><x-cms.status :status="$st" />@if ($st === 'SCHEDULED')<div class="mt-0.5 text-xs text-stone-500">{{ Cms::when($i['publishedAt'] ?? null) }}</div>@endif</td>
                                        @break
                                    @case('date')
                                        <td class="whitespace-nowrap text-sm text-stone-600" data-sort="{{ $i[$arg] ?? '' }}">{{ Cms::day($i[$arg] ?? null) }}</td>
                                        @break
                                    @case('when')
                                        <td class="whitespace-nowrap text-sm" data-sort="{{ $i[$arg] ?? '' }}">{{ Cms::when($i[$arg] ?? null) }}</td>
                                        @break
                                    @case('repeat')
                                        <td class="text-sm text-stone-600">{{ strtoupper((string) ($i['recurrence'] ?? 'NONE')) === 'WEEKLY' ? 'Weekly'.(! empty($i['recurrenceUntil']) ? ' until '.Cms::day($i['recurrenceUntil']) : '') : 'Once' }}</td>
                                        @break
                                    @case('label')
                                        <td class="text-sm">{{ \App\Support\Cms\Resources::EVENT_CATEGORIES[$i[$arg] ?? ''] ?? ($i[$arg] ?? '-') }}</td>
                                        @break
                                    @case('bool')
                                        <td class="text-sm" data-sort="{{ ($i[$arg] ?? false) ? 1 : 0 }}">@if ($i[$arg] ?? false)<span class="inline-flex items-center gap-1 text-brand-700"><x-icon name="star" class="size-4" /> Yes</span>@else<span class="text-stone-400">No</span>@endif</td>
                                        @break
                                    @case('count')
                                        <td class="num tabular-nums">{{ $i[$arg] ?? 0 }}</td>
                                        @break
                                    @case('tags')
                                        <td class="text-xs">@forelse ((array) ($i['tags'] ?? []) as $t)<span class="mr-1 inline-block rounded-full bg-stone-100 px-2 py-0.5 text-stone-600">{{ $t }}</span>@empty<span class="text-stone-400">-</span>@endforelse</td>
                                        @break
                                    @default
                                        <td class="text-sm" data-sort="{{ data_get($i, $arg ?? $ck) }}">{{ data_get($i, $arg ?? $ck) ?? '-' }}</td>
                                @endswitch
                            @endforeach
                            <td class="whitespace-nowrap text-right">
                                <div class="inline-flex flex-wrap items-center justify-end gap-x-3 gap-y-1 text-sm font-medium">
                                    <a class="text-brand-700 underline" href="{{ route('website.'.$key.'.edit', $id) }}">{{ $canManage ? 'Edit' : 'View' }}</a>
                                    @if ($def['publish'] && $canPublish)
                                        @if (in_array($st, ['DRAFT', 'ARCHIVED'], true))
                                            <form method="POST" action="{{ route('website.'.$key.'.transition', [$id, 'publish']) }}" class="inline">@csrf<button class="text-brand-700 underline" data-testid="publish">Publish</button></form>
                                        @else
                                            <form method="POST" action="{{ route('website.'.$key.'.transition', [$id, 'unpublish']) }}" class="inline" x-data="confirmSubmit('Unpublish this {{ strtolower($def['label']) }}? It disappears from the website but you keep it as a draft.', 'Unpublish')" @submit="ask($event)">@csrf<button class="text-stone-700 underline" data-testid="unpublish">Unpublish</button></form>
                                        @endif
                                        @if ($st !== 'ARCHIVED')
                                            <form method="POST" action="{{ route('website.'.$key.'.transition', [$id, 'archive']) }}" class="inline" x-data="confirmSubmit('Archive this {{ strtolower($def['label']) }}? It is hidden from the website and moved out of the way.', 'Archive')" @submit="ask($event)">@csrf<button class="text-stone-700 underline" data-testid="archive">Archive</button></form>
                                        @endif
                                    @endif
                                    @if ($liveOnSite($i))<a class="inline-flex items-center gap-1 text-stone-600 underline" href="{{ Cms::siteUrl($site($i)) }}" target="_blank" rel="noopener">View on site <x-icon name="external" class="size-3.5" /></a>@endif
                                    @if ($canManage)
                                        <form method="POST" action="{{ route('website.'.$key.'.destroy', $id) }}" class="inline" x-data="confirmSubmit('Delete this {{ strtolower($def['label']) }} for good? This cannot be undone.', 'Delete')" @submit="ask($event)">@csrf @method('DELETE')<button class="text-red-700 underline" data-testid="delete">Delete</button></form>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="{{ count($def['columns']) + 1 }}"><x-empty title="Nothing matches" text="Try a different search or filter." icon="search"><x-btn class="mt-3" variant="secondary" :href="route('website.'.$key.'.index')">Clear filters</x-btn></x-empty></td></tr>
                    @endforelse
                    </tbody>
                </table>
                </div>
                <x-pagination :count="count($items)" :next="$next" :noun="strtolower($def['plural'])" />
            @endif
        @endif
    </x-card>
</x-cms.layout>
