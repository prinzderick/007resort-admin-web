@props(['name', 'label' => null, 'type' => 'text', 'value' => null, 'hint' => null, 'required' => false])
<x-field :name="$name" :label="$label ?? ucfirst($name)" :type="$type" :value="$value" :hint="$hint" :required="$required" {{ $attributes }} />
