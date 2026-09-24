@props(['value', 'currency' => 'NGN'])
@php $zero = trim((string) $value) !== '' && \App\Support\Money::cmp($value, '0') === 0; $neg = str_starts_with(trim((string) $value), '-') && \App\Support\Money::cmp($value, '0') < 0; @endphp
<span {{ $attributes->merge(['class' => 'tabular-nums whitespace-nowrap'.($neg ? ' text-red-700' : ($zero ? ' text-stone-400' : ''))]) }}>{{ \App\Support\Money::format($value, $currency) }}</span>
