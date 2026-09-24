@props(['entityId' => null, 'entityType' => null])
{{-- "Last changed by/when" for a settings screen, from the audit trail (needs audit.view), with a link to the full history. --}}
@php
    $meta = app(\App\Services\Portal\SettingsMeta::class)->last($entityType, $entityId);
@endphp
@if ($meta)
    <p class="mb-4 flex flex-wrap items-center gap-x-2 text-xs text-stone-500" data-testid="settings-meta">
        <x-icon name="clock" class="size-3.5" /> Last changed <b class="font-medium text-stone-700"><x-time :at="$meta['at']" ago /></b> by <b class="font-medium text-stone-700">{{ $meta['by'] }}</b> ({{ $meta['action'] }})
        @if (auth_staff()->can('audit.view'))<a class="font-medium text-brand-700 underline" href="{{ route('audit.index', array_filter(['entityType' => $entityType, 'entityId' => $entityId])) }}">Change history</a>@endif
    </p>
@elseif (auth_staff()->can('audit.view'))
    <p class="mb-4 text-xs text-stone-500"><a class="font-medium text-brand-700 underline" href="{{ route('audit.index', array_filter(['entityType' => $entityType, 'entityId' => $entityId])) }}">Change history</a></p>
@endif
