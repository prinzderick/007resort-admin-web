@props(['name' => null, 'label' => null, 'hint' => null, 'meta' => null, 'error' => null, 'errorKey' => null, 'required' => false, 'optional' => false, 'disabled' => false, 'readonly' => false, 'id' => null, 'bare' => false, 'loading' => false, 'value' => null, 'length' => 4, 'mask' => true, 'pad' => false])
{{-- PIN entry: one box per digit (auto-advance, paste, backspace) and an optional on-screen keypad (:pad="true") for tablets. Value is a digit string; fires "f-complete" when full. --}}
@php
    $f = \App\Support\Form\FormField::resolve($attributes, array_replace(get_defined_vars(), ['value' => (string) $value]));
    $by = $f->describedBy();
@endphp
<x-form.field :f="$f" type="pin" :label-for="false" :x-data="$f->xData('fPin', ['length' => (int) $length, 'mask' => $mask])" x-modelable="value" {{ $attributes }}>
    <div class="f-pin" role="group" aria-labelledby="{{ $f->id }}-label" @if ($by) aria-describedby="{{ $by }}" @endif>
        @for ($i = 0; $i < $length; $i++)
            <input type="text" x-ref="d{{ $i }}" inputmode="numeric" pattern="[0-9]*" maxlength="{{ $length }}" autocomplete="{{ $i === 0 ? 'one-time-code' : 'off' }}" aria-label="Digit {{ $i + 1 }} of {{ $length }}" :type="mask ? 'password' : 'text'"
                   :value="digits[{{ $i }}]" :disabled="disabled" :readonly="readonly" x-on:input="onInput($event, {{ $i }})" x-on:keydown="onKey($event, {{ $i }})" x-on:paste="onPaste($event, {{ $i }})" x-on:focus="$el.select()">
        @endfor
        <button type="button" class="f-icon-btn" :aria-label="mask ? 'Show PIN' : 'Hide PIN'" :aria-pressed="mask ? 'false' : 'true'" x-on:click="mask = !mask"><span x-show="mask"><x-form.icon name="eye" /></span><span x-show="!mask" x-cloak><x-form.icon name="eye-off" /></span></button>
    </div>
    @if ($pad)
        <div class="f-pad" role="group" aria-label="PIN keypad">
            @foreach ([1, 2, 3, 4, 5, 6, 7, 8, 9] as $k)<button type="button" x-on:click="press('{{ $k }}')" :disabled="disabled">{{ $k }}</button>@endforeach
            <button type="button" x-on:click="press('clear')" :disabled="disabled" aria-label="Clear" style="font-size:0.8125rem">Clear</button>
            <button type="button" x-on:click="press('0')" :disabled="disabled">0</button>
            <button type="button" x-on:click="press('back')" :disabled="disabled" aria-label="Delete last digit"><x-form.icon name="backspace" class="size-5" style="margin:auto" /></button>
        </div>
    @endif
    @if ($name)<input type="hidden" name="{{ $name }}" :value="value ?? ''" :disabled="disabled">@endif
</x-form.field>
