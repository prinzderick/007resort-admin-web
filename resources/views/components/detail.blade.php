@props(['title', 'fields' => [], 'href' => null, 'linkLabel' => 'Open full page', 'label' => 'Details'])
{{-- A "Details" button that slides the row's fields into the drawer (x-drawer in the layout). $fields: list of [label, value]. --}}
<button type="button" class="text-sm font-medium text-brand-700 underline decoration-brand-300 underline-offset-2 hover:decoration-brand-700"
        @click="$dispatch('open-drawer', {{ \Illuminate\Support\Js::from(['title' => $title, 'fields' => array_map(fn ($f) => [(string) $f[0], is_scalar($f[1] ?? null) || ($f[1] ?? null) === null ? (string) ($f[1] ?? '-') : json_encode($f[1])], $fields), 'href' => $href, 'linkLabel' => $linkLabel]) }})">{{ $label }}</button>
