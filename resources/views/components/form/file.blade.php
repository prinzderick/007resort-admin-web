@props(['name' => null, 'label' => null, 'hint' => null, 'meta' => null, 'error' => null, 'errorKey' => null, 'required' => false, 'optional' => false, 'disabled' => false, 'readonly' => false, 'id' => null, 'bare' => false, 'loading' => false, 'accept' => null, 'multiple' => false, 'maxSize' => null, 'maxFiles' => null, 'existing' => [], 'image' => false, 'prompt' => null])
{{-- File drop zone: drag and drop or browse, client-side size (:max-size in MB) and type (:accept) checks BEFORE anything uploads, per-file errors, image previews, upload progress
     (Livewire's livewire-upload-* events). wire:model / name go on the real <input type=file> (Livewire file uploads need that). :existing = [['name'=>..,'url'=>..,'size'=>..]] shows what is already stored. --}}
@php
    $accept = $accept ?? ($image ? 'image/png,image/jpeg,image/webp' : null);
    $f = \App\Support\Form\FormField::resolve($attributes, get_defined_vars());
    $by = $f->describedBy();
    $maxBytes = $maxSize ? (int) round($maxSize * 1024 * 1024) : null;
    $wire = $attributes->whereStartsWith('wire:model');
@endphp
<x-form.field :f="$f" type="{{ $image ? 'image' : 'file' }}" :x-data="$f->xData('fFile', ['accept' => $accept, 'maxBytes' => $maxBytes, 'multiple' => $multiple, 'maxFiles' => $maxFiles, 'existing' => $existing])" {{ $attributes->whereDoesntStartWith('wire:model') }}
              x-on:livewire-upload-progress="progress = $event.detail.progress" x-on:livewire-upload-finish="progress = null" x-on:livewire-upload-error="progress = null; localError = 'Upload failed. Try again.'">
    <div x-on:change.capture="onChange($event)">
        <div class="f-drop" :data-over="over ? 'true' : 'false'" :data-disabled="disabled ? 'true' : 'false'" x-on:dragover.prevent="over = canEdit" x-on:dragleave.prevent="over = false" x-on:drop.prevent="drop($event)">
            <input id="{{ $f->id }}" x-ref="input" type="file" @if ($name) name="{{ $name }}{{ $multiple ? '[]' : '' }}" @endif @if ($accept) accept="{{ $accept }}" @endif @if ($multiple) multiple @endif {{ $wire }} @if ($by) aria-describedby="{{ $by }}" @endif @if ($f->invalid()) aria-invalid="true" @endif :disabled="disabled" @if ($disabled) disabled @endif>
            <x-form.icon name="upload" class="size-6" />
            <strong>{{ $prompt ?? ($multiple ? 'Drop files here or browse' : ($image ? 'Drop an image here or browse' : 'Drop a file here or browse')) }}</strong>
            <span style="font-size:0.75rem">@if ($accept){{ collect(explode(',', $accept))->map(fn ($a) => strtoupper(ltrim(trim(str_replace('image/', '', $a)), '.')))->implode(', ') }}@endif @if ($maxSize) &middot; up to {{ rtrim(rtrim(number_format($maxSize, 1), '0'), '.') }} MB @endif @if ($maxFiles) &middot; max {{ $maxFiles }} files @endif</span>
        </div>
        <div class="f-progress" x-show="progress !== null" x-cloak role="progressbar" aria-label="Upload progress" :aria-valuenow="progress" aria-valuemin="0" aria-valuemax="100"><i :style="'width:' + (progress || 0) + '%'"></i></div>
    </div>
    <div class="f-files" x-show="files.length || existing.length" x-cloak>
        <template x-for="(e, i) in existing" :key="'e' + i"><div class="f-file"><template x-if="e.url && {{ $image ? 'true' : 'false' }}"><img class="f-thumb-img" :src="e.url" alt=""></template><div class="f-file-meta"><div class="f-file-name" x-text="e.name"></div><div class="f-file-sub">Current file</div></div><button type="button" class="f-icon-btn" :aria-label="'Remove ' + e.name" :disabled="!canEdit" x-on:click="removeExisting(i)"><x-form.icon name="x" /></button></div></template>
        <template x-for="(fl, i) in files" :key="'n' + i">
            <div class="f-file" :data-invalid="fl.error ? 'true' : 'false'">
                <template x-if="fl.url"><img class="f-thumb-img" :src="fl.url" alt=""></template>
                <template x-if="!fl.url"><span class="f-card-icon"><x-form.icon name="file" /></span></template>
                <div class="f-file-meta"><div class="f-file-name" x-text="fl.name"></div><div class="f-file-sub" :style="fl.error ? 'color:var(--color-danger-800)' : ''" x-text="fl.error || size(fl.size)"></div></div>
                <button type="button" class="f-icon-btn" :aria-label="'Remove ' + fl.name" x-on:click="fl.error ? dismiss(i) : remove(i)"><x-form.icon name="x" /></button>
            </div>
        </template>
    </div>
    @if ($name && $existing)<input type="hidden" name="{{ $name }}_remove" :value="removedExisting ? '1' : '0'">@endif
</x-form.field>
