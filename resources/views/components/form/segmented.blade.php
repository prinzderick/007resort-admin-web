@props(['name' => null, 'label' => null, 'hint' => null, 'meta' => null, 'error' => null, 'errorKey' => null, 'required' => false, 'optional' => false, 'disabled' => false, 'readonly' => false,
        'id' => null, 'bare' => false, 'value' => null, 'options' => [], 'loading' => false, 'block' => false, 'pills' => false, 'multiple' => false])
{{-- Segmented control (or radio pills with :pills=true). One choice; native radios underneath, so arrows and form posting work. --}}
@php
    $opts = \App\Support\Form\FormField::options($options);
    $multiple = false; $variant = $pills ? 'pills' : 'segmented'; $cols = null;
    $f = \App\Support\Form\FormField::resolve($attributes, array_replace(get_defined_vars(), ['value' => $value]));
@endphp
<x-form.field :f="$f" type="segmented" :label-for="false" :x-data="$f->xData('fChoice', ['options' => $opts, 'multiple' => $multiple])" x-modelable="value" {{ $attributes }}>
    @include('components.form._choice', ['f' => $f, 'variant' => $variant, 'opts' => $opts, 'multiple' => $multiple, 'name' => $name, 'cols' => $cols, 'block' => $block])
</x-form.field>
