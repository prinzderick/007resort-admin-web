@props(['name' => null, 'label' => null, 'hint' => null, 'meta' => null, 'error' => null, 'errorKey' => null, 'required' => false, 'optional' => false, 'disabled' => false, 'readonly' => false, 'id' => null, 'bare' => false, 'loading' => false, 'value' => null, 'meter' => false, 'placeholder' => null, 'autocomplete' => 'new-password'])
{{-- Password: show/hide toggle, Caps Lock warning, optional strength meter. The value is never echoed back into the page (server side value is ignored). --}}
@php
    $f = \App\Support\Form\FormField::resolve($attributes, array_replace(get_defined_vars(), ['value' => '']));
    $by = $f->describedBy();
@endphp
<x-form.field :f="$f" type="password" :x-data="$f->xData('fPassword')" x-modelable="value" {{ $attributes }}>
    <div class="f-box" :data-disabled="disabled ? 'true' : 'false'" :data-readonly="readonly ? 'true' : 'false'">
        <input id="{{ $f->id }}" type="password" :type="show ? 'text' : 'password'" @if ($name) name="{{ $name }}" @endif class="f-input" x-model="value" placeholder="{{ $placeholder }}" autocomplete="{{ $autocomplete }}" autocapitalize="off" spellcheck="false"
               @if ($by) aria-describedby="{{ $by }}" @endif @if ($f->invalid()) aria-invalid="true" @endif @if ($required) aria-required="true" @endif :disabled="disabled" :readonly="readonly" x-on:keyup="keyCheck($event)" x-on:keydown="keyCheck($event)">
        <button type="button" class="f-icon-btn" :aria-label="show ? 'Hide password' : 'Show password'" :aria-pressed="show ? 'true' : 'false'" x-on:click="show = !show" :disabled="disabled"><span x-show="!show"><x-form.icon name="eye" /></span><span x-show="show" x-cloak><x-form.icon name="eye-off" /></span></button>
    </div>
    <p class="f-hint" x-show="caps" x-cloak style="color:var(--color-warning-800)">Caps Lock is on.</p>
    @if ($meter)
        <div class="f-strength" :data-level="strength" aria-hidden="true"><i></i><i></i><i></i><i></i></div>
        <p class="f-hint" x-show="strength" x-cloak x-text="'Strength: ' + strengthLabel" aria-live="polite"></p>
    @endif
</x-form.field>
