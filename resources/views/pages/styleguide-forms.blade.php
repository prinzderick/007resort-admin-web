@php
    use Illuminate\View\ComponentAttributeBag;

    $defs = json_decode((string) file_get_contents(resource_path('fixtures/rule-definitions.json')), true)['items'];
    $facilities = ['fac-1' => 'Main Restaurant', 'fac-2' => 'Poolside Bar', 'fac-3' => 'Spa', 'fac-4' => 'Main Reception'];
    $week = collect(['mon', 'tue', 'wed', 'thu', 'fri', 'sat', 'sun'])->mapWithKeys(fn ($d) => [$d => ['open' => $d !== 'sun', 'intervals' => $d === 'sat' ? [['from' => '09:00', 'to' => '14:00'], ['from' => '18:00', 'to' => '23:00']] : [['from' => '08:00', 'to' => '22:00']]]])->all();
    $err = 'This value is not allowed. The API rejected it (422).';
    $long = 'Maximum number of unpaid open tabs a single waiter may have running across all outdoor and indoor seating areas at any one time';
    $opts3 = [['value' => 'a', 'label' => 'Alpha'], ['value' => 'b', 'label' => 'Bravo'], ['value' => 'c', 'label' => 'Charlie']];
    $cards = [
        ['value' => 'A', 'label' => 'A: Reserved pool', 'description' => 'Keep taking bookings from a reserved share of units.', 'icon' => 'pie', 'badge' => 'Recommended'],
        ['value' => 'B', 'label' => 'B: Online only', 'description' => 'Bookings need the cloud; blocked while offline.', 'icon' => 'lock'],
        ['value' => 'C', 'label' => 'C: Switch off online', 'description' => 'Online booking is disabled while offline.', 'icon' => 'ban'],
    ];
    $selectOpts = collect(['Abuja', 'Benin City', 'Calabar', 'Enugu', 'Ibadan', 'Kano', 'Lagos', 'Port Harcourt', 'Uyo', 'Warri'])->map(fn ($c) => ['value' => strtolower($c), 'label' => $c, 'description' => $c === 'Lagos' ? 'Commercial capital' : null])->all();

    // Every control: [component, base props]. States below add error / disabled / readonly / loading / long label.
    $matrix = [
        'toggle' => ['base' => ['label' => 'Allow open tabs', 'description' => 'Customers can pay later and settle when they leave.', 'value' => true, 'name' => 'sg_toggle'], 'empty' => ['value' => false]],
        'slider' => ['base' => ['label' => 'Hold time', 'hint' => 'How long a slot is held.', 'value' => 30, 'min' => 5, 'max' => 120, 'step' => 5, 'unit' => ' min', 'ticks' => 5, 'snap' => [15, 30, 60], 'name' => 'sg_slider'], 'empty' => ['value' => 5]],
        'range' => ['base' => ['label' => 'Price band', 'hint' => 'Guests see rooms in this range.', 'value' => ['from' => 15000, 'to' => 60000], 'min' => 0, 'max' => 100000, 'step' => 1000, 'prefix' => '₦', 'format' => 'thousands', 'min-gap' => 5000, 'name' => 'sg_range'], 'empty' => ['value' => ['from' => 0, 'to' => 100000]]],
        'stepper' => ['base' => ['label' => 'Receipt copies', 'value' => 2, 'min' => 1, 'max' => 5, 'unit' => 'copies', 'name' => 'sg_stepper'], 'empty' => ['value' => 1]],
        'percent' => ['base' => ['label' => 'Late cancellation fee', 'hint' => 'Share of the booking total.', 'value' => 25, 'name' => 'sg_percent'], 'empty' => ['value' => 0]],
        'segmented' => ['base' => ['label' => 'Slot length', 'options' => ['15' => '15 min', '30' => '30 min', '60' => '60 min', '90' => '90 min'], 'value' => '30', 'name' => 'sg_seg'], 'empty' => ['value' => null]],
        'radio-cards' => ['base' => ['label' => 'Booking while offline', 'options' => $cards, 'value' => 'A', 'name' => 'sg_cards'], 'empty' => ['value' => null], 'wide' => true],
        'checkbox-group' => ['base' => ['label' => 'Always needs approval', 'options' => [['value' => 'void', 'label' => 'Void an order'], ['value' => 'discount', 'label' => 'Discount', 'description' => 'Any amount'], ['value' => 'refund', 'label' => 'Refund']], 'value' => ['void', 'refund'], 'name' => 'sg_checks', 'select-all' => true], 'empty' => ['value' => []]],
        'money' => ['base' => ['label' => 'Waiter cash limit', 'hint' => 'Emits a decimal string, e.g. 50000.00.', 'value' => '50000.00', 'quick' => ['20000', '50000', '100000'], 'name' => 'sg_money'], 'empty' => ['value' => null]],
        'duration' => ['base' => ['label' => 'Cancellation window', 'unit' => 'minutes', 'units' => ['minutes', 'hours', 'days'], 'value' => 120, 'min' => 0, 'max' => 10080, 'slider' => true, 'name' => 'sg_duration'], 'empty' => ['value' => null]],
        'time' => ['base' => ['label' => 'Opens at', 'value' => '08:00', 'name' => 'sg_time'], 'empty' => ['value' => null]],
        'time-range' => ['base' => ['label' => 'Happy hour', 'value' => ['from' => '17:00', 'to' => '19:30'], 'name' => 'sg_trange'], 'empty' => ['value' => ['from' => null, 'to' => null]]],
        'select' => ['base' => ['label' => 'City', 'options' => $selectOpts, 'value' => 'lagos', 'name' => 'sg_select', 'clearable' => true], 'empty' => ['value' => null]],
        'date' => ['base' => ['label' => 'Start date', 'value' => '2026-09-24', 'name' => 'sg_date'], 'empty' => ['value' => null]],
        'date-range' => ['base' => ['label' => 'Report period', 'value' => ['from' => '2026-09-20', 'to' => '2026-09-26'], 'name' => 'sg_drange'], 'empty' => ['value' => ['from' => null, 'to' => null]]],
        'text' => ['base' => ['label' => 'Business name', 'value' => '007 Resort & Spa', 'clearable' => true, 'name' => 'sg_text', 'hint' => 'Shown on receipts.'], 'empty' => ['value' => '']],
        'textarea' => ['base' => ['label' => 'Receipt footer', 'value' => 'Thank you for visiting 007 Resort & Spa.', 'maxlength' => 120, 'name' => 'sg_ta', 'rows' => 3], 'empty' => ['value' => '']],
        'password' => ['base' => ['label' => 'New password', 'value' => '', 'meter' => true, 'name' => 'sg_pw'], 'empty' => ['value' => '']],
        'pin' => ['base' => ['label' => 'Manager PIN', 'value' => '12', 'length' => 4, 'name' => 'sg_pin', 'hint' => 'Four digits.'], 'empty' => ['value' => '']],
        'color' => ['base' => ['label' => 'Brand colour', 'value' => '#0f7d4f', 'name' => 'sg_color'], 'empty' => ['value' => '']],
        'file' => ['base' => ['label' => 'Supplier invoice', 'accept' => '.pdf,image/*', 'max-size' => 5, 'name' => 'sg_file', 'hint' => 'PDF or image up to 5 MB.'], 'empty' => []],
        'image' => ['base' => ['label' => 'Facility photo', 'max-size' => 2, 'name' => 'sg_image', 'existing' => [['name' => 'pool.jpg', 'url' => 'data:image/svg+xml;utf8,<svg xmlns=%22http://www.w3.org/2000/svg%22 width=%2248%22 height=%2248%22><rect width=%2248%22 height=%2248%22 fill=%22%231f9db5%22/></svg>']]], 'empty' => []],
        'tags' => ['base' => ['label' => 'Allowed email domains', 'value' => ['007resort.com', 'example.ng'], 'name' => 'sg_tags', 'max' => 8], 'empty' => ['value' => []]],
        'key-value' => ['base' => ['label' => 'Receipt extra lines', 'value' => [['key' => 'Wi-Fi', 'value' => 'Guest007'], ['key' => 'Reception', 'value' => 'Dial 0']], 'name' => 'sg_kv'], 'empty' => ['value' => []], 'wide' => true],
    ];
    $states = [
        'Default' => [], 'Empty' => 'empty', 'Focus (Tab into it)' => null,
        'Error' => ['error' => $err], 'Disabled' => ['disabled' => true], 'Read-only' => ['readonly' => true], 'Loading' => ['loading' => true], 'Long label' => ['label' => $long],
    ];
    unset($states['Focus (Tab into it)']);
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex,nofollow">
    <title>Form controls - style guide</title>
    @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    @endif
    @livewireStyles
</head>
<body class="min-h-screen bg-stone-100 text-stone-900 antialiased">
<main class="mx-auto w-full max-w-[90rem] px-4 py-6 lg:px-8">
    <header style="margin-bottom:1.5rem">
        <p class="f-sg-state" style="margin:0">Development only</p>
        <h1 class="t-page" style="font-size:1.75rem">Form controls</h1>
        <p class="t-caption" style="max-width:46rem;margin-top:0.25rem;font-size:0.875rem">Every <code>x-form.*</code> control in every state, the schema-driven rule renderer, and a Livewire-bound form. Tab through everything: each control is keyboard reachable and shows a focus ring. Full usage: <code>docs/FORM_COMPONENTS.md</code>.</p>
        <nav aria-label="Sections" style="display:flex;flex-wrap:wrap;gap:0.5rem;margin-top:1rem">
            <a class="f-preset" href="#rules">Rule renderer</a><a class="f-preset" href="#livewire">Livewire</a><a class="f-preset" href="#hours">Weekly hours</a><a class="f-preset" href="#mobile">Mobile width</a><a class="f-preset" href="#actions">Actions and confirm</a>
            @foreach ($matrix as $c => $_)<a class="f-preset" href="#c-{{ $c }}">{{ $c }}</a>@endforeach
        </nav>
    </header>

    {{-- ------------------------------------------------------------------------------------------------------- schema renderer --}}
    <form method="POST" action="#" onsubmit="return false" x-data id="rules">
        <x-form.section title="Schema-driven rules" description="Rendered from rule definitions (fixtures/rule-definitions.json): the renderer picks the control from the type, range and options. Change a High impact rule to see the confirmation." stacked>
            <div class="f-sg-grid" style="grid-template-columns:repeat(auto-fit,minmax(min(100%,20rem),1fr))">
                @foreach (['bool' => 'toggle', 'enum <= 4, described' => 'radio-cards', 'enum, short' => 'segmented', 'number 11-1000' => 'slider', 'number <= 10' => 'stepper', 'percent' => 'percent', 'duration' => 'duration', 'money' => 'money', 'time' => 'time', 'string' => 'text / textarea', 'multi_enum' => 'checkbox-group', 'facility' => 'select'] as $t => $c)
                    <div class="f-card" style="min-height:0;cursor:default;padding:0.5rem 0.75rem;align-items:center"><span class="f-card-body"><span class="f-card-title">{{ $t }}</span></span><span class="f-badge" data-tone="low">{{ $c }}</span></div>
                @endforeach
            </div>
        </x-form.section>
        <x-form.schema :definitions="$defs" name="rules" :values="['hold_ttl_seconds' => 1800, 'cancel_fee_percent' => 10, 'waiter_cash_holding' => true, 'require_approval_for' => ['order.void'], 'receipt_copies' => 2, 'approval_threshold_amount' => '15000.0000', 'payment_facility_unit_id' => 'fac-4']"
                       :options="['payment_facility_unit_id' => $facilities]" :search="true" />
        <x-form.actions />
    </form>

    {{-- ------------------------------------------------------------------------------------------------------- livewire --}}
    <div id="livewire" style="margin-top:1.5rem"><livewire:forms-demo /></div>

    {{-- ------------------------------------------------------------------------------------------------------- weekly hours --}}
    <div id="hours" style="margin-top:1.5rem">
        <x-form.section title="Weekly hours" description="Open/closed per day, several intervals (split shifts), copy to weekdays, and exceptions for holidays." stacked>
            <x-form.weekly-hours name="hours" label="Opening hours" hint="Times are Lagos time." :value="['weekly' => $week, 'exceptions' => [['date' => '2026-12-25', 'label' => 'Christmas Day', 'open' => false, 'from' => null, 'to' => null]]]" />
            <x-form.weekly-hours label="Opening hours (error: overlap)" :value="['weekly' => array_merge($week, ['mon' => ['open' => true, 'intervals' => [['from' => '08:00', 'to' => '12:00'], ['from' => '11:00', 'to' => '15:00']]]]), 'exceptions' => []]" :exceptions="false" />
            <x-form.weekly-hours label="Opening hours (disabled)" :value="['weekly' => $week, 'exceptions' => []]" :disabled="true" :exceptions="false" />
        </x-form.section>
    </div>

    {{-- ------------------------------------------------------------------------------------------------------- state matrix --}}
    @foreach ($matrix as $c => $m)
        <x-form.section :id="'c-'.$c" :title="'x-form.'.$c" stacked>
            <div class="f-sg-grid" style="grid-template-columns:repeat(auto-fill,minmax(min(100%,{{ ($m['wide'] ?? false) ? '26rem' : '19rem' }}),1fr))">
                @foreach ($states as $state => $mod)
                    @php
                        $props = $m['base'];
                        if ($mod === 'empty') { $props = array_merge($props, $m['empty']); unset($props['hint']); }
                        elseif (is_array($mod)) { $props = array_merge($props, $mod); }
                        if ($state === 'Empty' && in_array($c, ['file', 'image'])) { unset($props['existing']); }
                        $props['name'] = null; $props['id'] = 'sg-'.$c.'-'.\Illuminate\Support\Str::slug($state);
                        if ($c === 'file' && $state !== 'Empty') { unset($props['existing']); }
                        $attributes = new ComponentAttributeBag($props);
                    @endphp
                    <div>
                        <div class="f-sg-state">{{ $state }}</div>
                        <x-dynamic-component :component="'form.'.$c" {{ $attributes }} />
                    </div>
                @endforeach
            </div>
        </x-form.section>
    @endforeach

    {{-- ------------------------------------------------------------------------------------------------------- mobile width --}}
    <div id="mobile"><x-form.section title="Mobile width (375px frame)" description="Same controls in a 375px column: touch targets stay 44px, nothing overflows." stacked>
        <div style="width:375px;max-width:100%;padding:1rem;border:1px dashed var(--color-stone-300);border-radius:1rem;display:grid;gap:1.25rem;background:#fff" data-testid="mobile-frame">
            <x-form.toggle label="Allow open tabs" description="Customers can pay later." :value="true" />
            <x-form.slider label="Hold time" :min="5" :max="120" :step="5" unit=" min" :value="30" :ticks="5" />
            <x-form.range label="Price band" :min="0" :max="100000" :step="1000" prefix="₦" format="thousands" :value="['from' => 15000, 'to' => 60000]" />
            <x-form.segmented label="Slot length" :options="['15' => '15', '30' => '30', '60' => '60', '90' => '90']" value="30" :block="true" />
            <x-form.money label="Waiter cash limit" value="50000.00" :quick="['20000', '50000', '100000']" />
            <x-form.duration label="Cancellation window" unit="minutes" :units="['minutes', 'hours', 'days']" :value="120" :min="0" :max="10080" :slider="true" />
            <x-form.time-range label="Happy hour" :value="['from' => '17:00', 'to' => '19:30']" />
            <x-form.radio-cards label="Payment timing" :options="[['value' => 'a', 'label' => 'Pay after service', 'description' => 'Guests pay at the end.', 'icon' => 'clipboard'], ['value' => 'b', 'label' => 'Pay first', 'description' => 'Paid before the kitchen starts.', 'icon' => 'bolt']]" value="a" />
            <x-form.pin label="Manager PIN" :pad="true" />
        </div>
    </x-form.section></div>

    {{-- ------------------------------------------------------------------------------------------------------- actions / confirm --}}
    <div id="actions"><x-form.section title="Actions and typed confirmation" description="Sticky save bar (Unsaved changes, loading, no double submit) and a typed confirmation for dangerous changes." stacked>
        <form onsubmit="event.preventDefault(); window.dispatchEvent(new Event('form-saved'));" style="display:grid;gap:1rem" data-testid="actions-demo">
            <x-form.text label="Facility name" value="Poolside Bar" name="fac_name" />
            <x-form.toggle label="Accept payments here" :value="true" name="fac_pay" />
            <x-form.actions :flush="false" style="position:static" />
        </form>
        <div>
            <x-form.confirm phrase="DISABLE PAYMENTS" title="Switch off payments?" message="Cashiers will not be able to take any payment at this facility until it is switched back on." confirm-label="Switch off payments">
                <x-slot:trigger><button type="button" class="f-btn" data-variant="danger" data-testid="confirm-trigger">Switch off payments...</button></x-slot:trigger>
            </x-form.confirm>
        </div>
    </x-form.section></div>
</main>
@livewireScripts
</body>
</html>
