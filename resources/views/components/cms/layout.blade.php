@props(['title'])
{{-- Every Website screen: the app shell plus the one shared image-picker dialog (fields and the Markdown editor open it). --}}
<x-layouts.app :title="$title">
    {{ $slot }}
    @if (auth_staff()->canAny('cms.view'))<x-cms.media-picker />@endif
</x-layouts.app>
