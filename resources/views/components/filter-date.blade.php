@props(['name', 'label', 'value' => null, 'width' => '10rem'])
@php $id = 'flt-'.preg_replace('/[^a-z0-9]+/i', '-', $name); @endphp
<div class="f-field" style="width:{{ $width }};max-width:100%"><label class="f-label" for="{{ $id }}" style="display:block;margin-bottom:0.25rem">{{ $label }}</label>
    <div class="f-box" style="min-height:2.5rem"><input id="{{ $id }}" type="date" name="{{ $name }}" value="{{ $value }}" class="f-input"></div></div>
