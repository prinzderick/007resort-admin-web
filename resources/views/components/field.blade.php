@props(['name', 'label', 'type' => 'text', 'value' => null, 'hint' => null, 'required' => false, 'options' => null, 'placeholder' => null])
@php
    // Form names like contact[phone] map to the dotted key Laravel uses for old input and for the API's field errors.
    $key = trim(str_replace(['[', ']'], ['.', ''], $name), '.');
    $id = 'f-'.preg_replace('/[^A-Za-z0-9_-]/', '-', $name);
@endphp
<div class="mb-3">
    <label for="{{ $id }}" class="mb-1 block text-sm font-medium text-stone-700">{{ $label }}@if ($required)<span class="text-red-600"> *</span>@endif</label>
    @if ($options !== null)
        <select id="{{ $id }}" name="{{ $name }}" {{ $required ? 'required' : '' }}
                class="min-h-11 w-full rounded-lg border border-stone-300 bg-white px-3 text-sm focus:border-brand-600 focus:outline-none">
            @foreach ($options as $k => $v)
                <option value="{{ $k }}" @selected((string) old($key, $value) === (string) $k)>{{ $v }}</option>
            @endforeach
        </select>
    @elseif ($type === 'textarea')
        <textarea id="{{ $id }}" name="{{ $name }}" rows="3" {{ $required ? 'required' : '' }}
                  class="w-full rounded-lg border border-stone-300 px-3 py-2 text-sm focus:border-brand-600 focus:outline-none">{{ old($key, $value) }}</textarea>
    @else
        <input id="{{ $id }}" name="{{ $name }}" type="{{ $type }}" value="{{ $type === 'password' ? '' : old($key, $value) }}"
               placeholder="{{ $placeholder }}" {{ $required ? 'required' : '' }} autocomplete="{{ $type === 'password' ? 'current-password' : 'off' }}"
               class="min-h-11 w-full rounded-lg border border-stone-300 px-3 text-sm focus:border-brand-600 focus:outline-none">
    @endif
    @if ($hint)<p class="mt-1 text-xs text-stone-500">{{ $hint }}</p>@endif
    @error($key)<p class="mt-1 text-xs text-red-700">{{ $message }}</p>@enderror
</div>
