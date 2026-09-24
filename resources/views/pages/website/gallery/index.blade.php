@php use App\Support\Cms\Cms; @endphp
<x-cms.layout title="Gallery">
    <x-page-header title="Gallery" subtitle="Photo albums for the website. Open an album to upload photos, drag them into order and add captions." :crumbs="['Website' => route('website.index'), 'Gallery' => null]">
        <x-slot:actions>@if ($canManage)<x-btn type="button" icon="plus" @click="$dispatch('open-modal', 'new-album')" data-testid="add-new">New album</x-btn>@endif</x-slot:actions>
    </x-page-header>
    <div class="mb-4"><x-cms.chips :options="$chips" :current="request('status', '')" :counts="$counts" /></div>
    <x-filter-form :reset="route('website.gallery')">
        <x-filter-text name="q" label="Search" :value="request('q')" placeholder="Album name" width="16rem" />
        @if (request('status'))<input type="hidden" name="status" value="{{ request('status') }}">@endif
    </x-filter-form>
    <x-card flush x-data="tableTools">
        <x-fetch :of="$list" what="Albums" />
        @if ($list->ok())
            @if ($items === [] && ! request()->hasAny(['q', 'status']))
                <x-empty title="No albums yet" text="Create an album, then upload the photos you want visitors to see." icon="image">@if ($canManage)<x-btn class="mt-4" type="button" icon="plus" @click="$dispatch('open-modal', 'new-album')" data-testid="add-first">Add first album</x-btn>@endif</x-empty>
            @else
                <x-table-tools placeholder="Filter these albums..." :csv="false" :selectable="false" :per-page="false" />
                <div class="overflow-x-auto"><table class="data-table" data-testid="content-table">
                    <thead><tr><th></th><th data-sort>Album</th><th data-sort class="num">Photos</th><th data-sort>Status</th><th data-sort>Last changed</th><th class="text-right">Actions</th></tr></thead>
                    <tbody>
                    @forelse ($items as $a)
                        @php $st = Cms::status($a); $th = Cms::thumb(is_array($a['cover'] ?? null) ? $a['cover'] : null, 240); @endphp
                        <tr data-row data-testid="row">
                            <td class="w-16">@if ($th)<img src="{{ $th }}" alt="" class="h-10 w-14 rounded-md object-cover" loading="lazy">@else<span class="flex h-10 w-14 items-center justify-center rounded-md bg-stone-100 text-stone-300"><x-icon name="image" class="size-4" /></span>@endif</td>
                            <td data-sort="{{ $a['title'] ?? '' }}"><a class="font-medium text-brand-700 underline decoration-brand-200 underline-offset-2" href="{{ route('website.gallery.show', $a['id']) }}">{{ $a['title'] ?? '(untitled)' }}</a><div class="text-xs text-stone-500">/{{ $a['slug'] ?? '' }}</div></td>
                            <td class="num tabular-nums">{{ $a['itemCount'] ?? 0 }}</td>
                            <td data-sort="{{ $st }}"><x-cms.status :status="$st" /></td>
                            <td class="whitespace-nowrap text-sm text-stone-600" data-sort="{{ $a['updatedAt'] ?? '' }}">{{ Cms::day($a['updatedAt'] ?? null) }}</td>
                            <td class="whitespace-nowrap text-right"><div class="inline-flex items-center gap-x-3 text-sm font-medium">
                                <a class="text-brand-700 underline" href="{{ route('website.gallery.show', $a['id']) }}">{{ $canManage ? 'Manage photos' : 'View' }}</a>
                                @if ($canPublish)
                                    @if (in_array($st, ['DRAFT', 'ARCHIVED'], true))<form method="POST" action="{{ route('website.gallery.transition', [$a['id'], 'publish']) }}" class="inline">@csrf<button class="text-brand-700 underline" data-testid="publish">Publish</button></form>
                                    @else<form method="POST" action="{{ route('website.gallery.transition', [$a['id'], 'unpublish']) }}" class="inline" x-data="confirmSubmit('Unpublish this album? It disappears from the website but you keep it as a draft.', 'Unpublish')" @submit="ask($event)">@csrf<button class="text-stone-700 underline" data-testid="unpublish">Unpublish</button></form>@endif
                                    @if ($st !== 'ARCHIVED')<form method="POST" action="{{ route('website.gallery.transition', [$a['id'], 'archive']) }}" class="inline" x-data="confirmSubmit('Archive this album? It is hidden from the website.', 'Archive')" @submit="ask($event)">@csrf<button class="text-stone-700 underline" data-testid="archive">Archive</button></form>@endif
                                @endif
                                @if ($canManage)<form method="POST" action="{{ route('website.gallery.destroy', $a['id']) }}" class="inline" x-data="confirmSubmit('Delete this album? The pictures stay in the media library.', 'Delete')" @submit="ask($event)">@csrf @method('DELETE')<button class="text-red-700 underline" data-testid="delete">Delete</button></form>@endif
                            </div></td>
                        </tr>
                    @empty
                        <tr><td colspan="6"><x-empty title="Nothing matches" text="Try a different search or filter." icon="search"><x-btn class="mt-3" variant="secondary" :href="route('website.gallery')">Clear filters</x-btn></x-empty></td></tr>
                    @endforelse
                    </tbody>
                </table></div>
                <x-pagination :count="count($items)" :next="$next" noun="albums" />
            @endif
        @endif
    </x-card>
    @if ($canManage)
        <x-dialog name="new-album" title="New album" subtitle="You can add photos on the next screen." maxWidth="max-w-lg">
            <form method="POST" action="{{ route('website.gallery.store') }}" class="grid gap-4" novalidate>@csrf
                <x-form.text name="title" label="Album name" required :maxlength="120" placeholder="e.g. Poolside" />
                <x-form.text :multiline="true" :rows="2" name="description" label="Description" :maxlength="1000" :optional="true" />
                <div class="flex justify-end gap-2"><x-btn type="button" variant="secondary" @click="open = false">Cancel</x-btn><x-btn>Create album</x-btn></div>
            </form>
        </x-dialog>
    @endif
</x-cms.layout>
