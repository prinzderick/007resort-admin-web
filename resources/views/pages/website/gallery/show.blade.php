@php
    use App\Support\Cms\Cms;
    $id = $album['id'];
    $live = in_array($status, ['PUBLISHED', 'SCHEDULED'], true);
    $coverId = $album['coverMediaId'] ?? null;
@endphp
<x-cms.layout :title="$album['title'] ?? 'Album'">
    <x-page-header :title="$album['title'] ?? 'Album'" :crumbs="['Website' => route('website.index'), 'Gallery' => route('website.gallery'), 'Album' => null]">
        <x-slot:actions>
            <x-cms.status :status="$status" />
            @if ($canPublish)
                @if ($live)<form method="POST" action="{{ route('website.gallery.transition', [$id, 'unpublish']) }}" x-data="confirmSubmit('Unpublish this album? It disappears from the website but you keep it as a draft.', 'Unpublish')" @submit="ask($event)">@csrf<x-btn variant="secondary" data-testid="unpublish">Unpublish</x-btn></form>
                @else<form method="POST" action="{{ route('website.gallery.transition', [$id, 'publish']) }}">@csrf<x-btn data-testid="publish">Publish album</x-btn></form>@endif
            @endif
            <x-btn variant="secondary" :href="route('website.gallery')" data-allow-leave>All albums</x-btn>
        </x-slot:actions>
    </x-page-header>
    @unless ($canManage)<x-alert tone="info">You can look at this album but not change it. Editing needs the "cms.manage" permission.</x-alert>@endunless

    @if ($canUpload)
        <x-card title="Add photos" subtitle="Drag photos from your computer, or pick from the media library.">
            <div class="grid gap-4 lg:grid-cols-[minmax(0,1fr)_16rem]">
                <x-cms.uploader :url="route('website.gallery.upload', $id)" :max-mb="$maxMb" label="Drag photos here to upload them into this album" />
                <div class="flex flex-col justify-center gap-2 rounded-xl border border-stone-200 bg-stone-50 p-4 text-center text-sm">
                    <p class="text-stone-600">Already uploaded a photo?</p>
                    <button type="button" class="f-btn" x-data @click="window.dispatchEvent(new CustomEvent('cms-pick-image', { detail: { resolve: (m) => { if (!m) return; const f = document.getElementById('add-from-library'); f.mediaId.value = m.id; f.submit(); } } }))" data-testid="add-from-library"><x-icon name="image" class="size-4" /> Choose from library</button>
                    <form id="add-from-library" method="POST" action="{{ route('website.gallery.add', $id) }}">@csrf<input type="hidden" name="mediaId"></form>
                </div>
            </div>
        </x-card>
    @endif

    <x-fetch :of="$itemsRes" what="The album photos" />
    <div x-data="{ f: {} }">
    <form method="POST" action="{{ route('website.gallery.update', $id) }}" x-data="cmsForm" novalidate data-testid="gallery-form">
        @csrf @method('PUT')
        <input type="hidden" name="rowVersion" value="{{ $album['rowVersion'] ?? '' }}">
        <x-card title="Album details">
            <div class="grid gap-4 md:grid-cols-2">
                <x-form.text name="title" label="Album name" :value="old('title', $album['title'] ?? '')" required :maxlength="120" :disabled="! $canManage" />
                <x-form.text name="slug" label="Web address" :value="old('slug', $album['slug'] ?? '')" prefix="/gallery/" :maxlength="120" :optional="true" :disabled="! $canManage" />
                <div class="md:col-span-2"><x-form.text :multiline="true" :rows="2" name="description" label="Description" :value="old('description', $album['description'] ?? '')" :maxlength="1000" :optional="true" :disabled="! $canManage" /></div>
                <x-form.text name="sortOrder" type="number" label="Position among albums" :value="old('sortOrder', $album['sortOrder'] ?? '')" hint="Lower numbers come first." :optional="true" :disabled="! $canManage" />
            </div>
        </x-card>

        <x-card :title="'Photos ('.count($items).')'" subtitle="Drag a photo, or use the arrow buttons, to change the order. Then press Save gallery." data-testid="photos-card">
            @if ($itemsRes->ok() && $items === [])
                <x-empty title="No photos in this album yet" text="Upload photos above and they appear here." icon="image" />
            @else
                <ul class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3" x-data="cmsSortable({ grid: true })" data-testid="photo-grid">
                    @foreach ($items as $n => $it)
                        @php $m = $it['_media']; $mid = $it['mediaId'] ?? ($m['id'] ?? ''); $isCover = $coverId && $coverId === $mid; $label = $it['caption'] ?: ($m['alt'] ?? 'Photo '.($n + 1)); @endphp
                        <li class="overflow-hidden rounded-xl border border-stone-200 bg-white shadow-sm" data-sort-item data-sort-id="{{ $it['id'] }}" data-sort-label="{{ $label }}" @if ($canManage) draggable="true" @dragstart="start($event, $el)" @dragover="over($event, $el)" @drop.prevent @dragend="end()" @endif data-testid="photo">
                            <input type="hidden" name="items[{{ $it['id'] }}][id]" value="{{ $it['id'] }}">
                            <div class="relative aspect-[4/3] bg-stone-100">
                                @if ($m)<img src="{{ Cms::thumb($m, 480) }}" alt="{{ $it['altText'] ?: ($m['alt'] ?? '') }}" class="size-full object-cover" loading="lazy">@endif
                                <span class="absolute left-2 top-2 flex size-7 items-center justify-center rounded-full bg-white/90 text-xs font-semibold shadow" data-sort-pos>{{ $n + 1 }}</span>
                                @if ($isCover)<span class="absolute right-2 top-2 rounded-full bg-brand-600 px-2.5 py-1 text-xs font-semibold text-white shadow" data-testid="cover-badge">Album cover</span>@endif
                                @if ($canManage)
                                    <div class="absolute bottom-2 left-2 flex gap-1">
                                        <button type="button" class="flex size-8 items-center justify-center rounded-lg bg-white/90 shadow hover:bg-white" data-move="up" @click="up($el.closest('[data-sort-item]'))" aria-label="Move {{ $label }} earlier"><x-icon name="up" class="size-4 -rotate-90" /></button>
                                        <button type="button" class="flex size-8 items-center justify-center rounded-lg bg-white/90 shadow hover:bg-white" data-move="down" @click="down($el.closest('[data-sort-item]'))" aria-label="Move {{ $label }} later"><x-icon name="down" class="size-4 -rotate-90" /></button>
                                    </div>
                                @endif
                            </div>
                            <div class="grid gap-3 p-3">
                                <x-form.text :name="'items['.$it['id'].'][caption]'" label="Caption" :value="old('items.'.$it['id'].'.caption', $it['caption'] ?? '')" :maxlength="300" :optional="true" :disabled="! $canManage" />
                                <x-form.text :name="'items['.$it['id'].'][altText]'" label="Description for screen readers (alt text)" :value="old('items.'.$it['id'].'.altText', $it['altText'] ?? '')" :maxlength="200" :optional="true" :disabled="! $canManage" />
                                <x-form.text :name="'items['.$it['id'].'][tags]'" label="Tags" :value="old('items.'.$it['id'].'.tags', implode(', ', (array) ($it['tags'] ?? [])))" hint="Separate with commas." :optional="true" :disabled="! $canManage" />
                                <x-form.toggle :name="'items['.$it['id'].'][featured]'" label="Featured photo" description="Shown first and on the home page." :value="(bool) old('items.'.$it['id'].'.featured', $it['featured'] ?? false)" :card="true" :disabled="! $canManage" />
                                @if ($canManage)
                                    <div class="flex items-center justify-between text-sm font-medium">
                                        @if (! $isCover)<button type="submit" form="cover-{{ $it['id'] }}" class="text-brand-700 underline" data-testid="set-cover">Set as album cover</button>@else<span class="text-stone-500">This is the cover</span>@endif
                                        <button type="submit" form="rm-{{ $it['id'] }}" class="text-red-700 underline" data-testid="remove-photo">Remove</button>
                                    </div>
                                @endif
                            </div>
                        </li>
                    @endforeach
                </ul>
            @endif
        </x-card>
        @if ($canManage)<x-form.actions submit="Save gallery" cancel="Discard changes" />@endif
    </form>
    </div>
    @if ($canManage)
        @foreach ($items as $it)
            @php $mid = $it['mediaId'] ?? ($it['_media']['id'] ?? ''); @endphp
            <form id="cover-{{ $it['id'] }}" method="POST" action="{{ route('website.gallery.cover', $id) }}">@csrf<input type="hidden" name="mediaId" value="{{ $mid }}"></form>
            <form id="rm-{{ $it['id'] }}" method="POST" action="{{ route('website.gallery.item.destroy', [$id, $it['id']]) }}" x-data="confirmSubmit('Remove this photo from the album? It stays in the media library.', 'Remove')" @submit="ask($event)">@csrf @method('DELETE')</form>
        @endforeach
    @endif
</x-cms.layout>
