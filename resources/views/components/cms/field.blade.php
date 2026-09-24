@props(['spec', 'values' => [], 'media' => [], 'options' => [], 'canEdit' => true])
{{-- One form field from a definition array (see App\Support\Cms\Resources): type text | textarea | markdown | slug | image | toggle | select | segmented | tags | datetime | date | number | url. --}}
@php
    $n = $spec['name'];
    $fm = "f['".$n."']";
    $type = $spec['type'] ?? 'text';
    $val = old(\App\Support\Form\FormField::dot($n), $values[$n] ?? ($spec['default'] ?? null));
    $common = ['name' => $n, 'label' => $spec['label'] ?? null, 'hint' => $spec['hint'] ?? null, 'required' => (bool) ($spec['required'] ?? false), 'optional' => ! ($spec['required'] ?? false), 'disabled' => ! $canEdit];
    $opts = $spec['options'] ?? ($options[$spec['optionsFrom'] ?? ''] ?? []);
@endphp
@switch($type)
    @case('markdown')
        <x-cms.markdown :name="$n" :label="$spec['label']" :hint="$spec['hint'] ?? null" :value="$val" :required="$spec['required'] ?? false" :can-pick="$canEdit && auth_staff()->can('cms.view')" :model="$fm" />
        @break
    @case('image')
        <x-cms.image :name="$n" :label="$spec['label']" :hint="$spec['hint'] ?? null" :value="$val" :media="$media[$n] ?? null" :required="$spec['required'] ?? false" :ratio="$spec['ratio'] ?? '16/9'" :can-pick="$canEdit" />
        @break
    @case('datetime')
        <x-cms.datetime :name="$n" :label="$spec['label']" :hint="$spec['hint'] ?? null" :value="$val" :required="$spec['required'] ?? false" :model="$fm" />
        @break
    @case('date')
        <x-cms.datetime :name="$n" :label="$spec['label']" :hint="$spec['hint'] ?? null" :value="$val" :required="$spec['required'] ?? false" :date-only="true" :model="$fm" />
        @break
    @case('toggle')
        <x-form.toggle :name="$n" :label="$spec['label']" :description="$spec['hint'] ?? null" :value="(bool) $val" :card="true" :disabled="! $canEdit" x-model="f['{{ $n }}']" />
        @break
    @case('select')
        <x-form.select :name="$n" :label="$spec['label']" :hint="$spec['hint'] ?? null" :options="$opts" :value="$val" :required="$spec['required'] ?? false" :optional="! ($spec['required'] ?? false)" :searchable="count($opts) > 8" :clearable="! ($spec['required'] ?? false)" :placeholder="$spec['placeholder'] ?? 'Choose...'" :disabled="! $canEdit" x-model="f['{{ $n }}']" />
        @break
    @case('segmented')
        <x-form.segmented :name="$n" :label="$spec['label']" :hint="$spec['hint'] ?? null" :options="$opts" :value="$val" :disabled="! $canEdit" x-model="f['{{ $n }}']" />
        @break
    @case('tags')
        <x-form.tags :name="$n" :label="$spec['label']" :hint="$spec['hint'] ?? null" :value="(array) ($val ?? [])" :max="$spec['max'] ?? null" :max-length="$spec['maxLength'] ?? null" :lowercase="$spec['lowercase'] ?? false" :placeholder="$spec['placeholder'] ?? 'Type a tag and press Enter'" :optional="true" :disabled="! $canEdit" />
        @break
    @case('textarea')
        <x-form.text :multiline="true" :rows="$spec['rows'] ?? 3" :name="$n" :label="$spec['label']" :hint="$spec['hint'] ?? null" :value="$val" :maxlength="$spec['max'] ?? null" :required="$spec['required'] ?? false" :optional="! ($spec['required'] ?? false)" :placeholder="$spec['placeholder'] ?? ''" :disabled="! $canEdit" x-model="f['{{ $n }}']" />
        @break
    @case('slug')
        <div x-data="cmsSlug({ source: @js('f-'.($spec['source'] ?? 'title')), value: @js((string) $val) })" x-effect="f['{{ $n }}'] = slug" data-testid="slug-field">
            <x-form.text :name="$n" :label="$spec['label'] ?? 'Web address (slug)'" :hint="$spec['hint'] ?? 'Made from the title. Change it only if you need a different address; lowercase letters, numbers and dashes.'" :value="$val" :maxlength="120" :prefix="$spec['prefix'] ?? '/'" :optional="true" :disabled="! $canEdit" x-model="slug" @input="edit()" />
        </div>
        @break
    @default
        <x-form.text :name="$n" :id="'f-'.$n" :type="$type === 'number' ? 'number' : ($type === 'url' ? 'url' : 'text')" :inputmode="$type === 'number' ? 'numeric' : null" :label="$spec['label']" :hint="$spec['hint'] ?? null" :value="$val" :maxlength="$spec['max'] ?? null" :prefix="$spec['prefix'] ?? null" :suffix="$spec['suffix'] ?? null" :required="$spec['required'] ?? false" :optional="! ($spec['required'] ?? false)" :placeholder="$spec['placeholder'] ?? ''" :disabled="! $canEdit" x-model="f['{{ $n }}']" />
@endswitch
