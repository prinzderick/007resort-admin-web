@props(['name', 'label', 'type' => 'text', 'value' => null, 'hint' => null, 'required' => false, 'options' => null, 'placeholder' => null])
<div class="mb-3">
    <label for="f-{{ $name }}" class="mb-1 block text-sm font-medium text-stone-700">{{ $label }}@if ($required)<span class="text-red-600"> *</span>@endif</label>
    @if ($options !== null)
        <select id="f-{{ $name }}" name="{{ $name }}" {{ $required ? 'required' : '' }}
                class="min-h-11 w-full rounded-lg border border-stone-300 bg-white px-3 text-sm focus:border-stone-900 focus:outline-none">
            @foreach ($options as $k => $v)
                <option value="{{ $k }}" @selected((string) old($name, $value) === (string) $k)>{{ $v }}</option>
            @endforeach
        </select>
    @elseif ($type === 'textarea')
        <textarea id="f-{{ $name }}" name="{{ $name }}" rows="3" {{ $required ? 'required' : '' }}
                  class="w-full rounded-lg border border-stone-300 px-3 py-2 text-sm focus:border-stone-900 focus:outline-none">{{ old($name, $value) }}</textarea>
    @else
        <input id="f-{{ $name }}" name="{{ $name }}" type="{{ $type }}" value="{{ $type === 'password' ? '' : old($name, $value) }}"
               placeholder="{{ $placeholder }}" {{ $required ? 'required' : '' }} autocomplete="{{ $type === 'password' ? 'current-password' : 'off' }}"
               class="min-h-11 w-full rounded-lg border border-stone-300 px-3 text-sm focus:border-stone-900 focus:outline-none">
    @endif
    @if ($hint)<p class="mt-1 text-xs text-stone-500">{{ $hint }}</p>@endif
    @error($name)<p class="mt-1 text-xs text-red-700">{{ $message }}</p>@enderror
</div>
