@props(['name' => null, 'label' => null, 'hint' => null, 'meta' => null, 'error' => null, 'errorKey' => null, 'required' => false, 'optional' => false, 'disabled' => false, 'readonly' => false,
        'id' => null, 'bare' => false, 'value' => null, 'options' => [], 'loading' => false, 'cols' => null, 'min' => null, 'max' => null, 'selectAll' => false])
{{-- A list of checkboxes with optional descriptions. Model is an array of the ticked values; posts name[]. --}}
@php
    $opts = \App\Support\Form\FormField::options($options);
    $variant = 'checks'; $multiple = true; $block = false; $value = (array) $value;
    $f = \App\Support\Form\FormField::resolve($attributes, array_replace(get_defined_vars(), ['value' => $value]));
@endphp
<x-form.field :f="$f" type="checkbox-group" :label-for="false" :x-data="$f->xData('fChoice', ['options' => $opts, 'multiple' => true, 'min' => $min, 'max' => $max])" x-modelable="value" {{ $attributes }}>
    @if ($selectAll)<div style="margin-bottom:0.25rem"><button type="button" class="f-link" x-on:click="toggleAll()" x-text="allOn ? 'Clear all' : 'Select all'">Select all</button></div>@endif
    @include('components.form._choice', ['f' => $f, 'variant' => $variant, 'opts' => $opts, 'multiple' => $multiple, 'name' => $name, 'cols' => $cols, 'block' => $block])
</x-form.field>
