@props(['name', 'label', 'options' => [], 'value' => null, 'all' => 'All', 'auto' => true, 'width' => '11rem'])
@php $opts = \App\Support\Form\FormField::options($options); $id = 'flt-'.preg_replace('/[^a-z0-9]+/i', '-', $name); @endphp
<div class="f-field" style="width:{{ $width }};max-width:100%"><label class="f-label" for="{{ $id }}" style="display:block;margin-bottom:0.25rem">{{ $label }}</label>
    <div class="f-box" style="min-height:2.5rem"><select id="{{ $id }}" name="{{ $name }}" class="f-input" style="cursor:pointer" @if ($auto) onchange="this.form.submit()" @endif>
        @if ($all !== null)<option value="">{{ $all }}</option>@endif
        @foreach ($opts as $o)<option value="{{ $o['value'] }}" @selected((string) $value === (string) $o['value'])>{{ $o['label'] }}</option>@endforeach
    </select></div></div>
