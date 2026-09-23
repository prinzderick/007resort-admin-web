@props(['at', 'ago' => false])
<span title="{{ $at }}" class="whitespace-nowrap">{{ $ago ? \App\Support\Time::ago($at) : \App\Support\Time::format($at) }}</span>
