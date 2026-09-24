@props(['at', 'ago' => false])
@php $t = \App\Support\Time::parse($at); $abs = \App\Support\Time::format($at); $rel = \App\Support\Time::ago($at); @endphp
<time @if ($t) datetime="{{ $t->toIso8601ZuluString() }}" @endif title="{{ $ago ? $abs : $rel }} (Africa/Lagos)" class="whitespace-nowrap">{{ $ago ? $rel : $abs }}</time>
