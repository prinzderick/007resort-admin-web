@props(['name', 'label' => null, 'hint' => null, 'value' => null, 'media' => null, 'required' => false, 'ratio' => '16/9', 'canPick' => true, 'error' => null, 'model' => null])
{{-- Image field: shows the chosen picture, opens the shared picker (choose from the library or upload there). Posts the media id. `media` is the expanded Media object when known. --}}
@php
    $id = 'img-'.preg_replace('/[^a-z0-9]+/i', '-', $name);
    $key = \App\Support\Form\FormField::dot($name);
    $err = $errors->first($key);
    $url = \App\Support\Cms\Cms::thumb(is_array($media) ? $media : null, 800);
    $val = old(\App\Support\Form\FormField::dot($name), $value);
    if (old(\App\Support\Form\FormField::dot($name)) !== null && old(\App\Support\Form\FormField::dot($name)) !== $value) { $url = ''; }
@endphp
<div class="f-field" data-f="image" data-invalid="{{ $err ? 'true' : 'false' }}" x-data="cmsImage({ id: @js($val ?: ''), url: @js($url), alt: @js(is_array($media) ? ($media['alt'] ?? '') : '') })" @if ($model) x-effect="{{ $model }} = id; f['{{ $name }}:url'] = url" @endif data-testid="image-field-{{ $name }}">
    @if ($label)<div class="f-head"><span class="f-label" id="{{ $id }}-label">{{ $label }}@if ($required)<span class="f-req" aria-hidden="true">*</span>@endif</span>@if (! $required)<span class="f-opt">Optional</span>@endif</div>@endif
    <div class="flex items-start gap-3">
        <div class="relative flex w-40 shrink-0 items-center justify-center overflow-hidden rounded-lg border border-stone-200 bg-stone-100" style="aspect-ratio: {{ $ratio }}">
            <template x-if="url"><img :src="url" :alt="alt || ''" class="size-full object-cover"></template>
            <template x-if="!url"><span class="flex flex-col items-center gap-1 px-2 text-center text-xs text-stone-500"><x-icon name="image" class="size-6" />No picture</span></template>
        </div>
        <div class="flex min-w-0 flex-col items-start gap-2">
            @if ($canPick)
                <button type="button" class="f-btn" data-size="sm" @click="choose()" aria-labelledby="{{ $id }}-label" data-testid="choose-image"><span x-text="id ? 'Change picture' : 'Choose picture'">Choose picture</span></button>
                <button type="button" class="f-link" x-show="id" x-cloak @click="clear()">Remove picture</button>
            @else
                <span class="text-xs text-stone-500">You can view pictures but not change them.</span>
            @endif
            <span class="text-xs text-stone-500" x-show="alt" x-cloak>Alt text: <span x-text="alt"></span></span>
        </div>
    </div>
    <input type="hidden" name="{{ $name }}" :value="id" value="{{ $val }}">
    @if ($hint)<p class="f-hint">{{ $hint }}</p>@endif
    @if ($err)<p class="f-error" role="alert">{{ $err }}</p>@endif
</div>
