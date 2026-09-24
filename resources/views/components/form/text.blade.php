@props(['name' => null, 'label' => null, 'hint' => null, 'meta' => null, 'error' => null, 'errorKey' => null, 'required' => false, 'optional' => false, 'disabled' => false, 'readonly' => false, 'id' => null, 'bare' => false, 'loading' => false, 'value' => null, 'type' => 'text', 'prefix' => null, 'suffix' => null, 'maxlength' => null, 'counter' => null, 'clearable' => false, 'placeholder' => null, 'autocomplete' => 'off', 'inputmode' => null,
        'multiline' => false, 'rows' => 3, 'autosize' => false])
{{-- Text input (or textarea with :multiline): prefix/suffix inside the border, character counter, clear button. Native <input name=...> so classic posts work; wire:model / x-model bind the same value. --}}
@php
    $f = \App\Support\Form\FormField::resolve($attributes, get_defined_vars());
    $by = $f->describedBy();
    $showCounter = $counter ?? ($maxlength !== null && $multiline);
    $val = $value === null ? '' : (string) $value;
@endphp
<x-form.field :f="$f" type="{{ $multiline ? 'textarea' : 'text' }}" :x-data="$f->xData('fText', ['maxlength' => $maxlength, 'autosize' => $autosize])" x-modelable="value" {{ $attributes }}>
    <div class="f-box" :data-disabled="disabled ? 'true' : 'false'" :data-readonly="readonly ? 'true' : 'false'" @if ($multiline) style="align-items:flex-start" @endif>
        @if ($prefix)<span class="f-affix">{{ $prefix }}</span>@endif
        @if ($multiline)
            <textarea id="{{ $f->id }}" x-ref="input" @if ($name) name="{{ $name }}" @endif rows="{{ $rows }}" class="f-input" x-model="value" placeholder="{{ $placeholder }}" @if ($maxlength) maxlength="{{ $maxlength + 100 }}" @endif
                      @if ($by) aria-describedby="{{ $by }}" @endif @if ($f->invalid()) aria-invalid="true" @endif @if ($required) aria-required="true" @endif :disabled="disabled" :readonly="readonly" @if ($autosize) x-on:input="grow()" @endif>{{ $val }}</textarea>
        @else
            <input id="{{ $f->id }}" x-ref="input" type="{{ $type }}" @if ($name) name="{{ $name }}" @endif value="{{ $val }}" class="f-input" x-model="value" placeholder="{{ $placeholder }}" autocomplete="{{ $autocomplete }}" @if ($inputmode) inputmode="{{ $inputmode }}" @endif
                   @if ($maxlength) maxlength="{{ $maxlength + 100 }}" @endif @if ($by) aria-describedby="{{ $by }}" @endif @if ($f->invalid()) aria-invalid="true" @endif @if ($required) aria-required="true" @endif :disabled="disabled" :readonly="readonly" @if ($bare && $label) aria-label="{{ $label }}" @endif>
        @endif
        @if ($clearable)<button type="button" class="f-icon-btn" aria-label="Clear" x-show="hasValue && canEdit" x-cloak x-on:click="clear()"><x-form.icon name="x" /></button>@endif
        @if ($suffix)<span class="f-affix">{{ $suffix }}</span>@endif
    </div>
    @if ($showCounter && $maxlength)
        <div style="display:flex"><span class="f-counter" :data-over="over ? 'true' : 'false'" aria-live="polite"><span x-text="count">{{ mb_strlen($val) }}</span> / {{ $maxlength }}</span></div>
    @endif
</x-form.field>
