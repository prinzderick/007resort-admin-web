@props(['name' => null, 'label' => null, 'hint' => null, 'meta' => null, 'error' => null, 'errorKey' => null, 'required' => false, 'optional' => false, 'disabled' => false, 'readonly' => false, 'id' => null, 'bare' => false, 'loading' => false, 'value' => null, 'options' => [], 'multiple' => false, 'searchable' => true, 'placeholder' => 'Select...', 'searchPlaceholder' => 'Search...', 'clearable' => false, 'creatable' => false, 'endpoint' => null, 'loader' => null,
        'queryParam' => 'q', 'preload' => false, 'max' => null, 'emptyText' => 'No matches'])
{{-- Single or multiple select. Searchable by default; `endpoint` (GET ?q=) or `loader` (window.R007Forms.loaders[name]) load options asynchronously; `creatable` lets people add a new item
     (fires a bubbling "f-create" event with {label, resolve(id, label)} so the page can persist it). Multi posts name[]; model is an array. --}}
@php
    $opts = \App\Support\Form\FormField::options($options);
    $val = $multiple ? array_values((array) $value) : $value;
    $f = \App\Support\Form\FormField::resolve($attributes, array_replace(get_defined_vars(), ['value' => $val]));
    $by = $f->describedBy();
    $label1 = collect($opts)->first(fn ($o) => ! $multiple && $val !== null && (string) $o['value'] === (string) $val)['label'] ?? null;
@endphp
<x-form.field :f="$f" type="select" :x-data="$f->xData('fSelect', ['options' => $opts, 'multiple' => $multiple, 'creatable' => $creatable, 'endpoint' => $endpoint, 'loader' => $loader, 'queryParam' => $queryParam, 'preload' => $preload, 'max' => $max])" x-modelable="value" {{ $attributes }}>
    <div class="f-anchor" x-on:click.outside="close()" x-on:keydown="key($event)">
        <div class="f-box" :data-disabled="disabled ? 'true' : 'false'" :data-readonly="readonly ? 'true' : 'false'" @if ($multiple) x-on:click="openList()" style="cursor:text" @endif>
            @if ($multiple)
                <div class="f-select-btn" style="cursor:text">
                    <span class="f-chips" style="flex:1 1 auto;min-width:0">
                        <template x-for="o in chosen" :key="o.value"><span class="f-chip"><span x-text="o.label"></span><button type="button" :aria-label="'Remove ' + o.label" :disabled="!canEdit" x-on:click.stop="remove(o.value)"><x-form.icon name="x" class="size-3.5" /></button></span></template>
                        <input id="{{ $f->id }}" x-ref="search" type="text" class="f-input" x-model="query" role="combobox" aria-autocomplete="list" aria-haspopup="listbox" aria-controls="{{ $f->id }}-list" :aria-expanded="open ? 'true' : 'false'"
                               :aria-activedescendant="open ? '{{ $f->id }}-o' + active : null" x-on:focus="openList()" :placeholder="chosen.length ? '' : '{{ $placeholder }}'" autocomplete="off" @if ($by) aria-describedby="{{ $by }}" @endif @if ($f->invalid()) aria-invalid="true" @endif :disabled="disabled" :readonly="readonly || {{ $searchable ? 'false' : 'true' }}">
                    </span>
                    <x-form.icon name="chevron" class="f-caret" />
                </div>
            @else
                <button type="button" id="{{ $f->id }}" x-ref="trigger" class="f-select-btn" role="combobox" aria-haspopup="listbox" aria-controls="{{ $f->id }}-list" :aria-expanded="open ? 'true' : 'false'" @if ($by) aria-describedby="{{ $by }}" @endif @if ($f->invalid()) aria-invalid="true" @endif
                        @if ($required) aria-required="true" @endif :disabled="disabled || readonly" x-on:click="toggleList()">
                    <span x-show="chosen.length" @if (! $label1) x-cloak @endif x-text="summary" style="flex:1 1 auto;min-width:0;overflow-wrap:anywhere">{{ $label1 }}</span>
                    <span class="f-placeholder" x-show="!chosen.length" @if ($label1) x-cloak @endif style="flex:1 1 auto">{{ $placeholder }}</span>
                    <x-form.icon name="chevron" class="f-caret" />
                </button>
                @if ($clearable)<button type="button" class="f-icon-btn" aria-label="Clear selection" x-show="chosen.length && canEdit" x-cloak x-on:click="clear()"><x-form.icon name="x" /></button>@endif
            @endif
        </div>
        <div class="f-pop" x-show="open" x-cloak>
            @if (! $multiple && $searchable)
                <div class="f-box" style="margin-bottom:0.375rem;min-height:2.5rem"><x-form.icon name="search" class="size-4" style="align-self:center;margin-left:0.75rem;color:var(--color-stone-500)" /><input x-ref="search" type="text" class="f-input" x-model="query" placeholder="{{ $searchPlaceholder }}" aria-label="Search options" autocomplete="off" role="searchbox" style="min-height:2.375rem"></div>
            @endif
            <div x-ref="listbox" role="listbox" id="{{ $f->id }}-list" @if ($multiple) aria-multiselectable="true" @endif aria-labelledby="{{ $f->id }}-label">
                <template x-for="(row, i) in list" :key="row.kind + ':' + String(row.value) + ':' + i">
                    <div role="option" class="f-opt-item" :id="'{{ $f->id }}-o' + i" :aria-selected="row.kind === 'option' && isOn(row.value) ? 'true' : 'false'" :aria-disabled="row.disabled ? 'true' : 'false'" :data-active="active === i ? 'true' : 'false'" x-on:mouseenter="active = i" x-on:click="choose(row)">
                        <template x-if="row.kind === 'create'"><span>Create "<strong x-text="row.label"></strong>"</span></template>
                        <template x-if="row.kind === 'option'"><span><span x-text="row.label"></span><span class="f-opt-desc" x-show="row.description" x-text="row.description"></span></span></template>
                    </div>
                </template>
            </div>
            <div class="f-pop-empty" x-show="loading"><span class="f-spin" style="display:inline-block;vertical-align:-3px"></span> Loading...</div>
            <div class="f-pop-empty" x-show="loadError" x-cloak><span x-text="loadError"></span> <button type="button" class="f-link" x-on:click="load(query)">Retry</button></div>
            <div class="f-pop-empty" x-show="!loading && !loadError && list.length === 0">{{ $emptyText }}</div>
        </div>
    </div>
    @if ($name)
        @if ($multiple)<template x-for="v in (value || [])" :key="v"><input type="hidden" :name="'{{ $name }}[]'" :value="v" :disabled="disabled"></template>
        @else<input type="hidden" name="{{ $name }}" value="{{ $val }}" :value="value ?? ''" :disabled="disabled">@endif
    @endif
</x-form.field>
