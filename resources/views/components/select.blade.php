@props(['name', 'label' => null, 'options' => [], 'value' => null, 'hint' => null, 'required' => false])
<x-field :name="$name" :label="$label ?? ucfirst($name)" :options="$options" :value="$value" :hint="$hint" :required="$required" />
