@props(['name' => null, 'label' => null, 'hint' => null, 'meta' => null, 'error' => null, 'errorKey' => null, 'required' => false, 'optional' => false, 'disabled' => false, 'readonly' => false, 'id' => null, 'bare' => false, 'loading' => false, 'value' => [], 'keyLabel' => 'Key', 'valueLabel' => 'Value', 'keyPlaceholder' => 'e.g. header', 'valuePlaceholder' => 'e.g. value', 'keyPattern' => null, 'max' => null, 'addLabel' => 'Add row'])
{{-- Editable key / value pairs (receipt fields, labels, integration headers). Model: [{key, value}, ...]. Posts name[<key>]=value. Duplicate or empty keys are flagged. --}}
@php
    $rows = collect(is_array($value) && array_is_list($value) ? $value : collect($value ?? [])->map(fn ($v, $k) => ['key' => $k, 'value' => $v])->values()->all())->map(fn ($r) => ['key' => (string) ($r['key'] ?? ''), 'value' => (string) ($r['value'] ?? '')])->values()->all();
    $f = \App\Support\Form\FormField::resolve($attributes, array_replace(get_defined_vars(), ['value' => $rows]));
    $by = $f->describedBy();
@endphp
<x-form.field :f="$f" type="key-value" :label-for="false" :x-data="$f->xData('fKeyValue', ['keyPattern' => $keyPattern, 'max' => $max])" x-modelable="value" {{ $attributes }}>
    <div class="f-kv" x-ref="rows" role="group" aria-labelledby="{{ $f->id }}-label" @if ($by) aria-describedby="{{ $by }}" @endif>
        <template x-for="(row, i) in value" :key="i">
            <div class="f-kv-row" :data-invalid="rowInvalid(i) ? 'true' : 'false'">
                <div class="f-box"><input type="text" class="f-input" x-model="row.key" placeholder="{{ $keyPlaceholder }}" :aria-label="'{{ $keyLabel }} ' + (i + 1)" autocomplete="off" :disabled="disabled" :readonly="readonly"></div>
                <div class="f-box"><input type="text" class="f-input" x-model="row.value" placeholder="{{ $valuePlaceholder }}" :aria-label="'{{ $valueLabel }} ' + (i + 1)" autocomplete="off" :disabled="disabled" :readonly="readonly"></div>
                <button type="button" class="f-icon-btn" :aria-label="'Remove row ' + (i + 1)" :disabled="disabled || readonly" x-on:click="remove(i)"><x-form.icon name="trash" /></button>
            </div>
        </template>
        <div><button type="button" class="f-link" :disabled="disabled || readonly || atMax" x-on:click="add()">+ {{ $addLabel }}</button></div>
    </div>
    @if ($name)<template x-for="(row, i) in value.filter((r) => r.key.trim() !== '')" :key="i"><input type="hidden" :name="'{{ $name }}[' + row.key.trim() + ']'" :value="row.value" :disabled="disabled"></template>@endif
</x-form.field>
