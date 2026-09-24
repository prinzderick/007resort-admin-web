@props(['name', 'label', 'type' => 'text', 'value' => null, 'hint' => null, 'required' => false, 'options' => null, 'placeholder' => null, 'disabled' => false, 'readonly' => false, 'suffix' => null, 'prefix' => null, 'maxlength' => null, 'min' => null, 'max' => null, 'step' => null])
{{-- One-line adapter over the x-form.* control library: every page that says <x-field> gets the same label / hint / error / focus styling.
     Prefer the specific x-form.* control (slider, money, toggle, radio-cards, ...) where one fits; this is the fallback for plain text, number, date, select and password. --}}
@php
    $key = \App\Support\Form\FormField::dot($name);
    $val = $type === 'password' ? null : old($key, $value);
    $common = ['name' => $name, 'label' => $label, 'hint' => $hint, 'required' => $required, 'disabled' => $disabled, 'readonly' => $readonly];
    $optCount = $options !== null ? count(is_array($options) ? $options : iterator_to_array($options)) : 0;
@endphp
@if ($options !== null)
    <x-form.select {{ $attributes }} :name="$name" :label="$label" :hint="$hint" :required="$required" :disabled="$disabled" :options="$options" :value="$val" :searchable="$optCount > 8" :placeholder="$placeholder ?? 'Select...'" />
@elseif ($type === 'textarea')
    <x-form.text {{ $attributes }} :name="$name" :label="$label" :hint="$hint" :required="$required" :disabled="$disabled" :readonly="$readonly" :value="$val" :multiline="true" :rows="3" :maxlength="$maxlength" :placeholder="$placeholder" />
@elseif ($type === 'password')
    <x-form.password {{ $attributes->except('autocomplete') }} :autocomplete="$attributes->get('autocomplete', 'current-password')" :name="$name" :label="$label" :hint="$hint" :required="$required" :disabled="$disabled" :placeholder="$placeholder" />
@elseif ($type === 'date')
    <x-form.date {{ $attributes }} :name="$name" :label="$label" :hint="$hint" :required="$required" :disabled="$disabled" :value="$val" />
@elseif ($type === 'time')
    <x-form.time {{ $attributes }} :name="$name" :label="$label" :hint="$hint" :required="$required" :disabled="$disabled" :value="$val" />
@elseif ($type === 'number')
    <x-form.text {{ $attributes }} :name="$name" :label="$label" :hint="$hint" :required="$required" :disabled="$disabled" :readonly="$readonly" :value="$val" type="text" inputmode="decimal" :suffix="$suffix" :prefix="$prefix" :placeholder="$placeholder" />
@else
    <x-form.text {{ $attributes }} :name="$name" :label="$label" :hint="$hint" :required="$required" :disabled="$disabled" :readonly="$readonly" :value="$val" :type="$type" :suffix="$suffix" :prefix="$prefix" :maxlength="$maxlength" :placeholder="$placeholder" />
@endif
