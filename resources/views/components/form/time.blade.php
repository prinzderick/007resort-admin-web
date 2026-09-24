@props(['name' => null, 'label' => null, 'hint' => null, 'meta' => null, 'error' => null, 'errorKey' => null, 'required' => false, 'optional' => false, 'disabled' => false, 'readonly' => false, 'id' => null, 'bare' => false, 'loading' => false, 'value' => null, 'step' => 30, 'min' => '00:00', 'max' => null, 'format' => '12', 'allow24' => false, 'nullable' => true, 'placeholder' => 'e.g. 9:00 AM'])
{{-- Time of day. Type "9am", "21:30" or pick from the list. Model/posted value is 24-hour "HH:MM" ("24:00" only with :allow24 for end of day). --}}
@php
    $f = \App\Support\Form\FormField::resolve($attributes, get_defined_vars());
    $by = $f->describedBy();
    $shown = $value ? ($format === '24' ? $value : \App\Support\Form\Format::time12((string) $value)) : '';
@endphp
<x-form.field :f="$f" type="time" :x-data="$f->xData('fTime', ['step' => (int) $step, 'min' => $min, 'max' => $max, 'format' => $format, 'allow24' => $allow24, 'nullable' => $nullable])" x-modelable="value" {{ $attributes }}>
    <div class="f-anchor" x-on:click.outside="close()" x-on:keydown="key($event)">
        <div class="f-box" :data-disabled="disabled ? 'true' : 'false'" :data-readonly="readonly ? 'true' : 'false'">
            <input id="{{ $f->id }}" type="text" autocomplete="off" role="combobox" aria-autocomplete="none" aria-haspopup="listbox" aria-controls="{{ $f->id }}-list" :aria-expanded="open ? 'true' : 'false'" class="f-input f-num" value="{{ $shown }}" x-model="text" placeholder="{{ $placeholder }}"
                   @if ($bare && $label) aria-label="{{ $label }}" @endif @if ($by) aria-describedby="{{ $by }}" @endif @if ($f->invalid()) aria-invalid="true" @endif @if ($required) aria-required="true" @endif :disabled="disabled" :readonly="readonly" x-on:blur="commit()" x-on:focus="$el.select()">
            <button type="button" class="f-icon-btn" tabindex="-1" aria-label="Choose a time" :disabled="disabled || readonly" x-on:click="open ? close() : openList()"><x-form.icon name="clock" /></button>
        </div>
        <div class="f-pop" x-show="open" x-cloak x-ref="list" role="listbox" id="{{ $f->id }}-list" aria-label="Times">
            <template x-for="(t, i) in times" :key="t"><div role="option" class="f-opt-item f-num" :aria-selected="value === t ? 'true' : 'false'" :data-active="active === i ? 'true' : 'false'" x-on:mouseenter="active = i" x-on:click="choose(t)" x-text="show(t)"></div></template>
        </div>
    </div>
    @if ($name)<input type="hidden" name="{{ $name }}" value="{{ $value }}" :value="value ?? ''" :disabled="disabled">@endif
</x-form.field>
