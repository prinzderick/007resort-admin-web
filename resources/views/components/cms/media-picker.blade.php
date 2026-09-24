{{-- The shared picture picker. One per page; image fields and the Markdown editor open it with the "cms-pick-image" event. Library search, tag filter,
     upload right here (drag or choose), and a crop preview showing how the picture will look wide, standard and square. --}}
@php $canUpload = auth_staff()->can('cms.media.manage'); @endphp
<div x-data="cmsMediaPicker({ listUrl: @js(route('website.media.picker')), uploadUrl: @js(route('website.media.upload')) })" x-init="listen()" @keydown.escape.window="if (open) close()" data-component="media-picker" x-cloak>
    <template x-teleport="body">
        <div x-show="open" x-cloak class="fixed inset-0 z-[70] flex items-start justify-center overflow-y-auto bg-stone-900/50 p-3 sm:items-center sm:p-6" @click.self="close()">
            <div class="flex max-h-[92vh] w-full max-w-6xl flex-col overflow-hidden rounded-2xl bg-white shadow-2xl" role="dialog" aria-modal="true" aria-labelledby="mp-title" x-trap.noscroll="open" data-testid="media-picker">
                <div class="flex items-center justify-between gap-3 border-b border-stone-100 px-5 py-3.5">
                    <div><h2 id="mp-title" class="t-section">Choose a picture</h2><p class="text-xs text-stone-500">Pick one from the library, or upload a new one.</p></div>
                    <button type="button" class="flex size-9 items-center justify-center rounded-full hover:bg-stone-100" @click="close()" aria-label="Close"><x-icon name="x" /></button>
                </div>
                <div class="grid min-h-0 flex-1 lg:grid-cols-[minmax(0,1fr)_20rem]">
                    <div class="flex min-h-0 flex-col border-b border-stone-100 lg:border-b-0 lg:border-r">
                        <div class="flex flex-wrap items-center gap-2 border-b border-stone-100 px-4 py-3">
                            <div class="relative min-w-40 flex-1"><x-icon name="search" class="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-stone-400" /><input type="search" x-model="q" @input="search()" placeholder="Search by name, description or credit" aria-label="Search pictures" class="min-h-10 w-full rounded-lg border border-stone-300 pl-9 pr-3 text-sm focus:border-brand-500 focus:outline-none"></div>
                            <select x-model="tag" @change="load(true)" aria-label="Filter by tag" class="min-h-10 rounded-lg border border-stone-300 bg-white px-2 text-sm"><option value="">All tags</option><template x-for="t in tags" :key="t"><option :value="t" x-text="t"></option></template></select>
                            @if ($canUpload)<label class="f-btn cursor-pointer" data-variant="primary" data-size="sm"><x-icon name="upload" class="size-4" /> Upload<input type="file" class="sr-only" multiple accept="image/jpeg,image/png,image/webp,image/avif" @change="upload($event.target.files); $event.target.value = ''" data-testid="picker-upload"></label>@endif
                        </div>
                        @if ($canUpload)
                        <div class="border-b border-stone-100 px-4 py-2 text-xs" x-show="uploads.length" x-cloak>
                            <template x-for="u in uploads" :key="u.name"><div class="flex items-center gap-2 py-0.5"><span class="truncate" x-text="u.name"></span><span :class="u.error ? 'text-red-700' : 'text-stone-500'" x-text="u.error || (u.done ? 'Uploaded' : u.progress + '%')"></span></div></template>
                        </div>
                        <div class="px-4 pt-3" @dragover.prevent="dropping = true" @dragleave.prevent="dropping = false" @drop.prevent="dropping = false; upload($event.dataTransfer.files)"><div class="rounded-lg border border-dashed px-3 py-2 text-center text-xs transition-colors" :class="dropping ? 'border-brand-600 bg-brand-50 text-brand-800' : 'border-stone-300 text-stone-500'">Drop pictures here to upload them now</div></div>
                        @endif
                        <div class="min-h-0 flex-1 overflow-y-auto p-4">
                            <p class="mb-2 text-sm text-red-700" x-show="error" x-text="error" role="alert"></p>
                            <ul class="grid grid-cols-2 gap-3 sm:grid-cols-3 xl:grid-cols-4" role="listbox" aria-label="Pictures">
                                <template x-for="m in items" :key="m.id">
                                    <li><button type="button" role="option" :aria-selected="selected && selected.id === m.id ? 'true' : 'false'" @click="pick(m)" @dblclick="pick(m); confirm()" class="group block w-full overflow-hidden rounded-lg border-2 text-left transition-colors focus-visible:outline-2" :class="selected && selected.id === m.id ? 'border-brand-600 ring-2 ring-brand-200' : 'border-stone-200 hover:border-stone-400'">
                                        <span class="block aspect-[4/3] bg-stone-100"><img :src="m.thumbUrl || m.url" :alt="m.alt || ''" loading="lazy" class="size-full object-cover"></span>
                                        <span class="block truncate px-2 py-1.5 text-xs text-stone-700" x-text="m.alt || m.originalName || 'Untitled'"></span>
                                    </button></li>
                                </template>
                            </ul>
                            <p class="py-10 text-center text-sm text-stone-500" x-show="!loading && !items.length && !error">No pictures found. @if ($canUpload)Upload the first one above.@endif</p>
                            <p class="py-6 text-center text-sm text-stone-500" x-show="loading">Loading pictures...</p>
                            <div class="mt-3 text-center" x-show="next && !loading"><button type="button" class="f-btn" data-size="sm" @click="load(false)">Show more</button></div>
                        </div>
                    </div>
                    <aside class="flex min-h-0 flex-col overflow-y-auto bg-stone-50 p-4" aria-label="Selected picture">
                        <template x-if="!selected"><div class="flex flex-1 items-center justify-center text-center text-sm text-stone-500">Select a picture to see how it will be cropped.</div></template>
                        <template x-if="selected">
                            <div class="space-y-3">
                                <div class="text-sm font-semibold text-stone-800" x-text="selected.alt || selected.originalName || 'Untitled'"></div>
                                <div class="text-xs text-stone-500"><span x-text="selected.width + ' x ' + selected.height"></span> &middot; <span x-text="bytes(selected.sizeBytes)"></span><template x-if="selected.credit"><span> &middot; <span x-text="selected.credit"></span></span></template></div>
                                <div class="t-label">How it will look</div>
                                <div class="grid grid-cols-2 gap-2">
                                    <div class="col-span-2"><div class="aspect-video overflow-hidden rounded-md border border-stone-200 bg-stone-200"><img :src="selected.url" alt="" class="size-full object-cover"></div><div class="mt-0.5 text-[11px] text-stone-500">Wide banner 16:9</div></div>
                                    <div><div class="aspect-[4/3] overflow-hidden rounded-md border border-stone-200 bg-stone-200"><img :src="selected.url" alt="" class="size-full object-cover"></div><div class="mt-0.5 text-[11px] text-stone-500">Card 4:3</div></div>
                                    <div><div class="aspect-square overflow-hidden rounded-md border border-stone-200 bg-stone-200"><img :src="selected.url" alt="" class="size-full object-cover"></div><div class="mt-0.5 text-[11px] text-stone-500">Square 1:1</div></div>
                                </div>
                                <p class="text-xs text-stone-500">Pictures are centred when cropped, so keep the subject near the middle.</p>
                                <p class="text-xs text-amber-700" x-show="!selected.alt">This picture has no description (alt text). Add one in the Media library so it reads well for everyone.</p>
                            </div>
                        </template>
                    </aside>
                </div>
                <div class="flex items-center justify-end gap-2 border-t border-stone-100 bg-white px-5 py-3">
                    <button type="button" class="f-btn" @click="close()">Cancel</button>
                    <button type="button" class="f-btn" data-variant="primary" :disabled="!selected" @click="confirm()" data-testid="picker-use">Use this picture</button>
                </div>
            </div>
        </div>
    </template>
</div>
