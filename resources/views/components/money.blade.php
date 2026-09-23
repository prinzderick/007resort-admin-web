@props(['value', 'currency' => 'NGN'])
<span class="tabular-nums">{{ \App\Support\Money::format($value, $currency) }}</span>
