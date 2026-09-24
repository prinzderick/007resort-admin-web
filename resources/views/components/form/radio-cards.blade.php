@props(['name' => null, 'label' => null, 'hint' => null, 'meta' => null, 'error' => null, 'errorKey' => null, 'required' => false, 'optional' => false, 'disabled' => false, 'readonly' => false,
        'id' => null, 'bare' => false, 'value' => null, 'options' => [], 'loading' => false, 'cols' => null, 'multiple' => false])
{{-- Large selectable cards with icon + description, e.g. booking strategy A/B/C or payment timing. One choice (or several with :multiple=true). --}}
@php
    $opts = \App\Support\Form\FormField::options($options);
    $variant = 'cards'; $block = false; $cols = $cols ?? (count($opts) === 2 ? 2 : null);
    $f = \App\Support\Form\FormField::resolve($attributes, array_replace(get_defined_vars(), ['value' => $value]));
@endphp
<x-form.field :f="$f" type="radio-cards" :label-for="false" :x-data="$f->xData('fChoice', ['options' => $opts, 'multiple' => $multiple])" x-modelable="value" {{ $attributes }}>
    @include('components.form._choice', ['f' => $f, 'variant' => $variant, 'opts' => $opts, 'multiple' => $multiple, 'name' => $name, 'cols' => $cols, 'block' => $block])
</x-form.field>
