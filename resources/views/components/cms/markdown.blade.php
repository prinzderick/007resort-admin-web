@props(['name', 'label' => null, 'hint' => null, 'value' => '', 'required' => false, 'rows' => 16, 'canPick' => true, 'model' => null])
{{-- Markdown editor: toolbar (bold, italic, headings, lists, quote, link, image from the library), side-by-side preview from lg up (Write / Preview tabs below). Posts the Markdown text. --}}
@php
    $val = (string) old(\App\Support\Form\FormField::dot($name), $value);
    $id = 'md-'.preg_replace('/[^a-z0-9]+/i', '-', $name);
    $key = \App\Support\Form\FormField::dot($name);
    $err = $errors->first($key);
    $btn = 'inline-flex min-h-9 min-w-9 items-center justify-center rounded-md border border-stone-200 bg-white px-2 text-sm font-medium text-stone-700 hover:bg-stone-50 focus-visible:outline-2';
@endphp
<div class="f-field" data-f="markdown" data-invalid="{{ $err ? 'true' : 'false' }}" x-data="cmsMarkdown({ value: @js($val) })" @if ($model) x-effect="{{ $model }} = value" @endif data-testid="markdown-{{ $name }}">
    @if ($label)<div class="f-head"><label for="{{ $id }}" class="f-label">{{ $label }}@if ($required)<span class="f-req" aria-hidden="true">*</span>@endif</label><span class="f-aside text-xs text-stone-500"><span x-text="words + ' words'"></span> &middot; <span x-text="readMinutes + ' min read'"></span></span></div>@endif
    <div class="overflow-hidden rounded-lg border border-stone-300 bg-white focus-within:border-brand-500">
        <div class="flex flex-wrap items-center gap-1 border-b border-stone-200 bg-stone-50 px-2 py-1.5" role="toolbar" aria-label="Formatting">
            <button type="button" class="{{ $btn }} font-bold" @click="apply('bold')" title="Bold (Ctrl/Cmd+B)" aria-label="Bold">B</button>
            <button type="button" class="{{ $btn }} italic" @click="apply('italic')" title="Italic (Ctrl/Cmd+I)" aria-label="Italic">I</button>
            <span class="mx-1 h-5 w-px bg-stone-200" aria-hidden="true"></span>
            <button type="button" class="{{ $btn }}" @click="apply('h2')" title="Heading" aria-label="Heading 2">H2</button>
            <button type="button" class="{{ $btn }}" @click="apply('h3')" title="Sub-heading" aria-label="Heading 3">H3</button>
            <span class="mx-1 h-5 w-px bg-stone-200" aria-hidden="true"></span>
            <button type="button" class="{{ $btn }}" @click="apply('ul')" title="Bulleted list" aria-label="Bulleted list">&bull; List</button>
            <button type="button" class="{{ $btn }}" @click="apply('ol')" title="Numbered list" aria-label="Numbered list">1. List</button>
            <button type="button" class="{{ $btn }}" @click="apply('quote')" title="Quote" aria-label="Quote">&ldquo; Quote</button>
            <span class="mx-1 h-5 w-px bg-stone-200" aria-hidden="true"></span>
            <button type="button" class="{{ $btn }}" @click="link()" title="Link (Ctrl/Cmd+K)" aria-label="Insert link"><x-icon name="link" class="size-4" /> Link</button>
            @if ($canPick)<button type="button" class="{{ $btn }}" @click="image()" title="Insert a picture from the library" aria-label="Insert image from library" data-testid="md-image"><x-icon name="image" class="size-4" /> Picture</button>@endif
            <span class="ml-auto flex gap-1 lg:hidden" role="tablist"><button type="button" role="tab" class="{{ $btn }}" :class="mode === 'write' && 'border-brand-600 text-brand-700'" @click="mode = 'write'">Write</button><button type="button" role="tab" class="{{ $btn }}" :class="mode === 'preview' && 'border-brand-600 text-brand-700'" @click="mode = 'preview'">Preview</button></span>
        </div>
        <div class="grid lg:grid-cols-2 lg:divide-x lg:divide-stone-200">
            <div :class="mode === 'preview' && 'max-lg:hidden'">
                <textarea id="{{ $id }}" x-ref="ta" name="{{ $name }}" x-model="value" rows="{{ $rows }}" @keydown="key($event)" class="block min-h-80 w-full resize-y border-0 p-3 font-mono text-sm leading-6 focus:outline-none" spellcheck="true" placeholder="Write here. Use the buttons above for headings, lists, links and pictures." @if ($err) aria-invalid="true" @endif></textarea>
            </div>
            <div :class="mode === 'write' && 'max-lg:hidden'" class="bg-stone-50/60">
                <div class="border-b border-stone-200 px-3 py-1.5 text-xs font-medium uppercase tracking-wide text-stone-500">Preview</div>
                <div class="cms-prose max-h-[32rem] overflow-y-auto p-4" x-html="html" aria-live="polite" data-testid="md-preview"></div>
                <p class="p-4 text-sm text-stone-400" x-show="!value.trim()" x-cloak>Nothing to preview yet.</p>
            </div>
        </div>
    </div>
    @if ($hint)<p class="f-hint">{{ $hint }}</p>@endif
    @if ($err)<p class="f-error" role="alert">{{ $err }}</p>@endif
</div>
