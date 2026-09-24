@props(['name', 'label' => null, 'hint' => null, 'value' => '', 'required' => false, 'model' => null, 'dateOnly' => false, 'nowButton' => false])
{{-- Date + time in Lagos time. Posts ONE value "YYYY-MM-DDTHH:MM" (Lagos wall clock); the server converts it to UTC for the API. --}}
@php
    $val = (string) old(\App\Support\Form\FormField::dot($name), $value);
    [$d, $t] = array_pad(explode('T', $val, 2), 2, '');
    $key = \App\Support\Form\FormField::dot($name);
    $err = $errors->first($key);
@endphp
<div class="f-field" data-f="datetime" data-invalid="{{ $err ? 'true' : 'false' }}" x-data="{ d: @js($d), t: @js($t), get v() { return this.d ? (@js($dateOnly) ? this.d : this.d + 'T' + (this.t || '00:00')) : '' } }" @if ($model) x-effect="{{ $model }} = v" @endif>
    @if ($label)<div class="f-head"><span class="f-label">{{ $label }}@if ($required)<span class="f-req" aria-hidden="true">*</span>@endif</span><span class="f-opt">Lagos time</span></div>@endif
    <div class="grid gap-2 sm:grid-cols-[minmax(0,1.3fr)_minmax(0,1fr)]">
        <x-form.date bare x-model="d" label="{{ $label }} date" placeholder="Date" />
        @unless ($dateOnly)<x-form.time bare x-model="t" label="{{ $label }} time" placeholder="Time" />@endunless
    </div>
    <input type="hidden" name="{{ $name }}" :value="v" value="{{ $val }}">
    @if ($hint)<p class="f-hint">{{ $hint }}</p>@endif
    @if ($err)<p class="f-error" role="alert">{{ $err }}</p>@endif
</div>
