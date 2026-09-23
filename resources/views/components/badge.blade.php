@props(['tone' => null, 'status' => null])
@php
    $tone ??= match (strtoupper((string) $status)) {
        'ACTIVE', 'ONLINE', 'CAPTURED', 'COMPLETED', 'APPROVED', 'APPLIED', 'SYNCED', 'OK', 'POSTED', 'CLOSED', 'RESOLVED', 'CONFIRMED', 'AVAILABLE' => 'good',
        'PENDING', 'PENDING_APPROVAL', 'QUEUED', 'SYNCING', 'DEGRADED', 'OPEN', 'AUTHORIZING', 'INITIATED', 'NEEDS_REVIEW', 'DRAFT', 'LOCAL', 'UNKNOWN', 'PARTIALLY_REFUNDED' => 'warn',
        'FAILED', 'OFFLINE', 'REVOKED', 'REJECTED', 'CONFLICT', 'SUSPENDED', 'TERMINATED', 'REVERSED', 'EXPIRED', 'DOWN', 'CANCELLED' => 'bad',
        default => 'default',
    };
    $cls = ['good' => 'bg-emerald-100 text-emerald-900', 'warn' => 'bg-amber-100 text-amber-900', 'bad' => 'bg-red-100 text-red-900', 'info' => 'bg-sky-100 text-sky-900', 'default' => 'bg-stone-100 text-stone-700'][$tone] ?? 'bg-stone-100 text-stone-700';
@endphp
<span {{ $attributes->merge(['class' => "inline-block whitespace-nowrap rounded-full px-2 py-0.5 text-xs font-semibold {$cls}"]) }}>{{ $slot->isEmpty() ? $status : $slot }}</span>
