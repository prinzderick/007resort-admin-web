@props(['name' => null, 'label' => null, 'hint' => null, 'meta' => null, 'error' => null, 'errorKey' => null, 'required' => false, 'optional' => false, 'disabled' => false, 'readonly' => false,
        'id' => null, 'bare' => false, 'value' => null, 'min' => 0, 'max' => 100, 'step' => 1, 'ticks' => 5, 'snap' => [0, 25, 50, 75, 100], 'loading' => false])
{{-- A percentage: slider (0-100) + number box + "%" suffix, with snap points at the quarters. Emits a plain number (e.g. 25 or 12.5). --}}
<x-form.slider :name="$name" :label="$label" :hint="$hint" :meta="$meta" :error="$error" :error-key="$errorKey" :required="$required" :optional="$optional" :disabled="$disabled" :readonly="$readonly"
               :id="$id" :bare="$bare" :value="$value" :min="$min" :max="$max" :step="$step" unit="%" :ticks="$ticks" :snap="$snap" :loading="$loading" {{ $attributes }} />
