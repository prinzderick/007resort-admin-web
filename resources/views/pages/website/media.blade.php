@php use App\Support\Cms\Cms; @endphp
<x-cms.layout title="Media library">
    <x-page-header title="Media library" subtitle="Every picture used on the website. Upload once, reuse anywhere. Give each picture a short description (alt text) so it works for everyone." :crumbs="['Website' => route('website.index'), 'Media library' => null]" />

    @if ($canUpload)
        <div class="mb-5"><x-cms.uploader :url="route('website.media.upload')" :max-mb="$maxMb" label="Drag pictures here to add them to the library" /></div>
    @endif

    <x-filter-form :reset="route('website.media')">
        <x-filter-text name="q" label="Search" :value="request('q')" placeholder="Name, description or credit" width="16rem" />
        <x-filter-select name="tag" label="Tag" :options="array_combine($tags, $tags) ?: []" :value="request('tag')" all="All tags" width="12rem" />
    </x-filter-form>

    <x-fetch :of="$list" what="The media library" />
    @if ($list->ok())
        @if ($items === [] && ! request()->hasAny(['q', 'tag']))
            <x-card><x-empty title="No pictures yet" text="Upload the first picture above. Once it is here you can pick it for the homepage, pages, blog and events." icon="image" /></x-card>
        @elseif ($items === [])
            <x-card><x-empty title="No pictures match" text="Try a different search or tag." icon="search"><x-btn class="mt-3" variant="secondary" :href="route('website.media')">Clear filters</x-btn></x-empty></x-card>
        @else
            <ul class="grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-4 2xl:grid-cols-6" data-testid="media-grid">
                @foreach ($items as $m)
                    <li class="overflow-hidden rounded-xl border border-stone-200 bg-white shadow-sm" data-testid="media-card" data-media-id="{{ $m['id'] }}">
                        <a href="{{ $m['url'] }}" target="_blank" rel="noopener" class="block aspect-[4/3] bg-stone-100"><img src="{{ $m['thumbUrl'] ?: $m['url'] }}" alt="{{ $m['alt'] }}" loading="lazy" class="size-full object-cover"></a>
                        <div class="space-y-1.5 p-3">
                            <div class="truncate text-sm font-medium text-stone-800" title="{{ $m['alt'] ?: $m['originalName'] }}">{{ $m['alt'] ?: ($m['originalName'] ?: 'Untitled') }}</div>
                            @if ($m['alt'] === '')<div class="text-xs text-amber-700">No description (alt text)</div>@endif
                            <div class="flex flex-wrap gap-1">@foreach ($m['tags'] as $t)<a href="{{ route('website.media', ['tag' => $t]) }}" class="rounded-full bg-stone-100 px-2 py-0.5 text-xs text-stone-600 hover:bg-stone-200">{{ $t }}</a>@endforeach</div>
                            <div class="flex items-center justify-between text-xs text-stone-500"><span>{{ $m['width'] }} x {{ $m['height'] }}</span><span data-testid="usage-count" class="{{ $m['usageCount'] ? 'font-medium text-brand-700' : '' }}">{{ $m['usageCount'] ? 'Used '.$m['usageCount'].($m['usageCount'] === 1 ? ' time' : ' times') : 'Not used' }}</span></div>
                            @if ($canUpload)
                                <div class="flex items-center justify-between pt-1 text-sm font-medium">
                                    <button type="button" class="text-brand-700 underline" @click="$dispatch('open-modal', { name: 'edit-media', data: @js($m) })" data-testid="edit-media">Edit</button>
                                    <button type="button" class="text-red-700 underline" @click="$dispatch('open-modal', { name: 'delete-media', data: @js($m) })" data-testid="delete-media">Delete</button>
                                </div>
                            @endif
                        </div>
                    </li>
                @endforeach
            </ul>
            <div class="mt-4"><x-card flush><x-pagination :count="count($items)" :next="$next" noun="pictures" /></x-card></div>
        @endif
    @endif

    @if ($canUpload)
        <x-dialog name="edit-media" title="Edit picture details" subtitle="The picture itself cannot be changed. Upload a new one to replace it." maxWidth="max-w-xl">
            <form method="POST" :action="'{{ url('/website/media') }}/' + payload.id" class="grid gap-4" novalidate x-data="cmsForm" data-testid="media-form">@csrf @method('PATCH')
                <div class="flex items-center gap-3"><img :src="payload.thumbUrl || payload.url" alt="" class="h-16 w-24 rounded-lg object-cover"><div class="text-xs text-stone-500"><div x-text="payload.originalName"></div><div><span x-text="payload.width + ' x ' + payload.height"></span></div></div></div>
                <x-form.text name="alt" label="Description (alt text)" hint="Describe what is in the picture, e.g. Guests relaxing by the pool. Read aloud by screen readers and shown if the picture cannot load." :maxlength="200" x-model="payload.alt" />
                <x-form.text name="credit" label="Photo credit" :maxlength="200" placeholder="Photo: Jane Doe" x-model="payload.credit" :optional="true" />
                <x-form.text name="sourceUrl" label="Source link" type="url" :maxlength="500" x-model="payload.sourceUrl" :optional="true" />
                <x-form.tags name="tags" label="Tags" :max="12" :max-length="32" :lowercase="true" hint="Words to find it by later, e.g. pool, food, event." x-model="payload.tags" />
                <div class="flex justify-end gap-2"><x-btn type="button" variant="secondary" @click="open = false">Cancel</x-btn><x-btn>Save details</x-btn></div>
            </form>
        </x-dialog>
        <x-dialog name="delete-media" title="Delete this picture?" subtitle="Pictures that are still used on the website cannot be deleted." maxWidth="max-w-lg">
            <div x-data="{ usage: null, err: '', load() { this.usage = null; this.err = ''; fetch('{{ url('/website/media') }}/' + payload.id + '/usage', { headers: { Accept: 'application/json' } }).then(r => r.json()).then(j => { this.usage = j; }).catch(() => { this.err = 'Could not check where this picture is used.'; }); } }" x-init="load()">
                <p class="text-sm text-stone-600" x-show="!usage && !err">Checking where it is used...</p>
                <p class="text-sm text-red-700" x-show="err" x-text="err"></p>
                <template x-if="usage && usage.usageCount > 0"><div data-testid="usage-blockers"><p class="mb-2 text-sm font-medium text-amber-800">It is used in <span x-text="usage.usageCount"></span> place(s). Replace it there first:</p><ul class="list-disc space-y-0.5 pl-5 text-sm"><template x-for="u in usage.usage" :key="u.type + u.id + u.field"><li><span x-text="u.type.replace('_', ' ')"></span>: <span x-text="u.label"></span></li></template></ul><div class="mt-4 flex justify-end"><x-btn type="button" variant="secondary" @click="open = false">Close</x-btn></div></div></template>
                <template x-if="usage && usage.usageCount === 0"><form method="POST" :action="'{{ url('/website/media') }}/' + payload.id" class="mt-1">@csrf @method('DELETE')<p class="mb-4 text-sm text-stone-700">It is not used anywhere. Deleting it removes the file for good.</p><div class="flex justify-end gap-2"><x-btn type="button" variant="secondary" @click="open = false">Keep it</x-btn><x-btn variant="danger" data-testid="confirm-delete-media">Delete picture</x-btn></div></form></template>
            </div>
        </x-dialog>
    @endif
</x-cms.layout>
