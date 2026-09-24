@props(['name' => null, 'label' => null, 'hint' => null, 'meta' => null, 'error' => null, 'errorKey' => null, 'required' => false, 'optional' => false, 'disabled' => false, 'readonly' => false, 'id' => null, 'bare' => false, 'loading' => false, 'value' => null, 'palette' => ['#0f7d4f', '#2b6cde', '#e2a72e', '#c9503c', '#7b5cd6', '#1f9db5', '#8a8f98', '#141a22'], 'on' => null])
{{-- Colour: hex text box + swatch + native colour picker + preset palette; warns when the colour is hard to read (WCAG contrast under 4.5:1 against `on`, default white). --}}
@php
    $f = \App\Support\Form\FormField::resolve($attributes, get_defined_vars());
    $by = $f->describedBy();
@endphp
<x-form.field :f="$f" type="color" :x-data="$f->xData('fColor', ['on' => $on])" x-modelable="value" {{ $attributes }}>
    <div class="f-box" :data-disabled="disabled ? 'true' : 'false'" :data-readonly="readonly ? 'true' : 'false'">
        <span class="f-swatch" :style="valid ? 'background:' + value : 'background:repeating-linear-gradient(45deg,#e3e7ec,#e3e7ec 4px,#fff 4px,#fff 8px)'" style="background:{{ $value ?: '#fff' }}" aria-hidden="true"></span>
        <input id="{{ $f->id }}" type="text" class="f-input f-num" x-model="text" placeholder="#0f7d4f" maxlength="7" autocomplete="off" spellcheck="false" @if ($by) aria-describedby="{{ $by }}" @endif @if ($f->invalid()) aria-invalid="true" @endif
               :disabled="disabled" :readonly="readonly" x-on:blur="commit()" x-on:keydown.enter="commit()" value="{{ $value }}">
        <label class="f-icon-btn" style="cursor:pointer" title="Open the colour picker"><x-form.icon name="pie" /><span class="sr-only">Pick a colour</span><input type="color" tabindex="-1" style="position:absolute;opacity:0;width:1px;height:1px" :value="valid ? value : '#000000'" :disabled="!canEdit" x-on:input="pick($event.target.value)"></label>
    </div>
    @if ($palette)<div class="f-palette" role="group" aria-label="Preset colours">@foreach ($palette as $c)<button type="button" style="background:{{ $c }}" aria-label="{{ $c }}" :aria-pressed="value === '{{ $c }}' ? 'true' : 'false'" :disabled="!canEdit" x-on:click="pick('{{ $c }}')"></button>@endforeach</div>@endif
    <p class="f-hint" x-show="valid && !readable" x-cloak style="color:var(--color-warning-800)" x-text="'Low contrast (' + ratioText + '); text may be hard to read.'"></p>
    @if ($name)<input type="hidden" name="{{ $name }}" value="{{ $value }}" :value="valid ? value : ''" :disabled="disabled">@endif
</x-form.field>
