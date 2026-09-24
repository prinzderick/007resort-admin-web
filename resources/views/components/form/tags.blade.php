@props(['name' => null, 'label' => null, 'hint' => null, 'meta' => null, 'error' => null, 'errorKey' => null, 'required' => false, 'optional' => false, 'disabled' => false, 'readonly' => false, 'id' => null, 'bare' => false, 'loading' => false, 'value' => [], 'placeholder' => 'Type and press Enter', 'max' => null, 'maxLength' => null, 'pattern' => null, 'patternMessage' => null, 'lowercase' => false, 'suggestions' => []])
{{-- Free-text chips: Enter / comma / paste adds, Backspace removes the last. Duplicates and (optional) pattern/length are rejected inline. Model is an array of strings; posts name[]. --}}
@php
    $val = array_values((array) $value);
    $f = \App\Support\Form\FormField::resolve($attributes, array_replace(get_defined_vars(), ['value' => $val]));
    $by = $f->describedBy();
@endphp
<x-form.field :f="$f" type="tags" :x-data="$f->xData('fTags', ['max' => $max, 'maxLength' => $maxLength, 'pattern' => $pattern, 'patternMessage' => $patternMessage, 'lowercase' => $lowercase])" x-modelable="value" {{ $attributes }}>
    <div class="f-box" :data-disabled="disabled ? 'true' : 'false'" :data-readonly="readonly ? 'true' : 'false'" style="padding:0.25rem 0.5rem;cursor:text" x-on:click="$refs.input.focus()">
        <div class="f-chips" style="flex:1 1 auto;min-width:0">
            <template x-for="(t, i) in value" :key="t + i"><span class="f-chip"><span x-text="t"></span><button type="button" :aria-label="'Remove ' + t" :disabled="!canEdit" x-on:click.stop="remove(i)"><x-form.icon name="x" class="size-3.5" /></button></span></template>
            <input id="{{ $f->id }}" x-ref="input" type="text" class="f-input" x-model="draft" placeholder="{{ $placeholder }}" autocomplete="off" @if ($suggestions) list="{{ $f->id }}-sugg" @endif @if ($by) aria-describedby="{{ $by }}" @endif @if ($f->invalid()) aria-invalid="true" @endif
                   :disabled="disabled" :readonly="readonly" x-on:keydown="key($event)" x-on:paste="paste($event)" x-on:blur="blur()">
        </div>
        @if ($suggestions)<datalist id="{{ $f->id }}-sugg">@foreach ($suggestions as $s)<option value="{{ $s }}"></option>@endforeach</datalist>@endif
    </div>
    @if ($name)<template x-for="(t, i) in value" :key="t + i"><input type="hidden" :name="'{{ $name }}[]'" :value="t" :disabled="disabled"></template>@endif
</x-form.field>
