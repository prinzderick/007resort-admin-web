@props(['url', 'fields' => [], 'maxMb' => 8, 'reload' => true, 'label' => 'Drag pictures here, or choose files', 'canUpload' => true])
{{-- Multi-file drag-and-drop uploader with a progress bar per file. Posts each file to `url` (a same-origin JSON endpoint). --}}
@if ($canUpload)
<div x-data="cmsUploader({ url: @js($url), fields: @js($fields), maxMb: {{ (int) $maxMb }}, reload: {{ $reload ? 'true' : 'false' }} })" data-testid="uploader" {{ $attributes }}>
    <div @dragover.prevent="dropping = true" @dragleave.prevent="dropping = false" @drop.prevent="drop($event)" :class="dropping ? 'border-brand-600 bg-brand-50' : 'border-stone-300 bg-white'"
         class="flex flex-col items-center justify-center gap-2 rounded-xl border-2 border-dashed px-6 py-8 text-center transition-colors">
        <x-icon name="upload" class="size-7 text-stone-400" />
        <div class="text-sm font-medium text-stone-800">{{ $label }}</div>
        <div class="text-xs text-stone-500">JPEG, PNG, WebP or AVIF, up to {{ $maxMb }} MB each. You can pick many at once.</div>
        <label class="f-btn mt-1 cursor-pointer" data-variant="primary" data-size="sm">Choose files<input type="file" class="sr-only" multiple accept="image/jpeg,image/png,image/webp,image/avif" @change="pick($event)" data-testid="upload-input"></label>
    </div>
    <ul class="mt-3 space-y-2" x-show="queue.length" x-cloak aria-live="polite">
        <template x-for="q in queue" :key="q.name + q.size">
            <li class="rounded-lg border border-stone-200 bg-white px-3 py-2 text-sm">
                <div class="flex items-center justify-between gap-3"><span class="truncate font-medium" x-text="q.name"></span><span class="shrink-0 text-xs" :class="q.state === 'failed' ? 'text-red-700' : (q.state === 'done' ? 'text-brand-700' : 'text-stone-500')" x-text="q.state === 'failed' ? 'Failed' : (q.state === 'done' ? 'Uploaded' : (q.state === 'uploading' ? q.progress + '%' : 'Waiting'))"></span></div>
                <div class="mt-1.5 h-1.5 overflow-hidden rounded-full bg-stone-100" x-show="q.state !== 'failed'"><div class="h-full rounded-full bg-brand-600 transition-all" :style="'width:' + q.progress + '%'"></div></div>
                <div class="mt-1 flex items-center justify-between gap-2 text-xs text-red-700" x-show="q.state === 'failed'"><span x-text="q.error"></span><button type="button" class="f-link" x-show="q.file && !q.error.startsWith('Only') && !q.error.startsWith('Larger')" @click="retry(q)">Try again</button></div>
            </li>
        </template>
    </ul>
</div>
@else
<x-alert tone="info">You can view the library but not upload. Ask an administrator for the "cms.media.manage" permission.</x-alert>
@endif
