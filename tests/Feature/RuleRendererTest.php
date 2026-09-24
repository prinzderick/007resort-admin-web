<?php

namespace Tests\Feature;

use App\Support\Form\RuleControl;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\MessageBag;
use Illuminate\Support\ViewErrorBag;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/** The schema-driven renderer: rule definition -> control mapping, badges, defaults, and the rendered schema form. */
class RuleRendererTest extends TestCase
{
    /** @return list<array<string, mixed>> */
    private function defs(): array
    {
        return json_decode((string) file_get_contents(resource_path('fixtures/rule-definitions.json')), true)['items'];
    }

    private function def(string $key): array
    {
        return collect($this->defs())->firstWhere('key', $key);
    }

    private function render(string $blade, array $vars = [], array $errors = []): string
    {
        view()->share('errors', (new ViewErrorBag)->put('default', new MessageBag($errors)));

        return Blade::render($blade, $vars);
    }

    public function test_fixtures_cover_the_shapes_the_api_publishes(): void
    {
        $defs = collect($this->defs());
        $this->assertGreaterThanOrEqual(25, $defs->count());
        foreach (['bool', 'enum', 'multi_enum', 'number', 'duration', 'money', 'string', 'time', 'facility'] as $type) {
            $this->assertTrue($defs->contains('type', $type), "fixtures include a $type rule");
        }
        foreach ($defs as $d) {
            foreach (['key', 'label', 'description', 'group', 'type', 'default', 'dangerLevel', 'enforcement'] as $k) {
                $this->assertArrayHasKey($k, $d, ($d['key'] ?? '?')." has $k");
            }
        }
    }

    /** The mapping table from the brief, asserted rule by rule. */
    public static function mapping(): array
    {
        return [
            'payment_timing' => ['payment_timing', 'radio-cards'],          // enum, 3 described options
            'stock_consumption_timing' => ['stock_consumption_timing', 'radio-cards'], // enum, 2 described options
            'allow_offline_payments' => ['allow_offline_payments', 'segmented'], // enum, 3 short options
            'booking_offline_strategy' => ['booking_offline_strategy', 'radio-cards'], // strategy A/B/C
            'require_cash_session' => ['require_cash_session', 'toggle'],
            'waiter_cash_holding' => ['waiter_cash_holding', 'toggle'],
            'waiter_cash_limit' => ['waiter_cash_limit', 'money'],
            'approval_threshold_amount' => ['approval_threshold_amount', 'money'],
            'require_approval_for' => ['require_approval_for', 'checkbox-group'],
            'receipt_copies' => ['receipt_copies', 'stepper'],               // number 1..5
            'max_reschedules' => ['max_reschedules', 'stepper'],             // number 0..20 is span 20 -> slider, see below
            'max_advance_days' => ['max_advance_days', 'slider'],            // 0..730
            'cancel_fee_percent' => ['cancel_fee_percent', 'percent'],
            'booking_local_reserve_percent' => ['booking_local_reserve_percent', 'percent'],
            'hold_ttl_seconds' => ['hold_ttl_seconds', 'duration'],
            'cancel_cutoff_minutes' => ['cancel_cutoff_minutes', 'duration'],
            'booking_online_stale_after_seconds' => ['booking_online_stale_after_seconds', 'duration'],
            'slot_granularity_minutes' => ['slot_granularity_minutes', 'segmented'], // number with allowed list
            'receipt_footer' => ['receipt_footer', 'textarea'],              // string, max 200
            'opening_time' => ['opening_time', 'time'],
            'payment_facility_unit_id' => ['payment_facility_unit_id', 'select'],
            'max_occupancy' => ['max_occupancy', 'stepper'],                 // unbounded-ish (1..100000) -> stepper
        ];
    }

    #[DataProvider('mapping')]
    public function test_each_rule_gets_the_right_control(string $key, string $expected): void
    {
        if ($key === 'max_reschedules') {
            $expected = 'slider'; // 0..20: span 20 is above the stepper threshold (10)
        }
        $this->assertSame($expected, RuleControl::describe($this->def($key))['control'], $key);
    }

    public function test_type_rules_on_synthetic_definitions(): void
    {
        $c = fn (array $d) => RuleControl::describe($d + ['key' => 'k', 'label' => 'K'])['control'];
        $this->assertSame('toggle', $c(['type' => 'bool']));
        $this->assertSame('segmented', $c(['type' => 'enum', 'allowed' => [['value' => 'a', 'label' => 'Yes'], ['value' => 'b', 'label' => 'No']]]));
        $this->assertSame('radio-cards', $c(['type' => 'enum', 'allowed' => [['value' => 'a', 'label' => 'Yes', 'description' => 'Does a'], ['value' => 'b', 'label' => 'No']]]));
        $this->assertSame('select', $c(['type' => 'enum', 'allowed' => array_map(fn ($i) => ['value' => "v$i", 'label' => "V$i"], range(1, 6))]));
        $this->assertSame('checkbox-group', $c(['type' => 'multi_enum', 'allowed' => [['value' => 'a', 'label' => 'A']]]));
        $this->assertSame('stepper', $c(['type' => 'number', 'min' => 1, 'max' => 10]), 'span 9');
        $this->assertSame('slider', $c(['type' => 'number', 'min' => 1, 'max' => 12]), 'span 11');
        $this->assertSame('slider', $c(['type' => 'number', 'min' => 0, 'max' => 1000]), 'span 1000');
        $this->assertSame('stepper', $c(['type' => 'number', 'min' => 0, 'max' => 5000]), 'span above 1000');
        $this->assertSame('stepper', $c(['type' => 'number']), 'unbounded');
        $this->assertSame('percent', $c(['type' => 'number', 'unit' => 'percent', 'min' => 0, 'max' => 100]));
        $this->assertSame('percent', $c(['type' => 'percent']));
        $this->assertSame('duration', $c(['type' => 'duration', 'unit' => 'minutes']));
        $this->assertSame('money', $c(['type' => 'money', 'unit' => 'NGN']));
        $this->assertSame('time', $c(['type' => 'time']));
        $this->assertSame('text', $c(['type' => 'string', 'max' => 60]));
        $this->assertSame('textarea', $c(['type' => 'string', 'max' => 500]));
        $this->assertSame('textarea', $c(['type' => 'string', 'multiline' => true]));
        $this->assertSame('slider', $c(['type' => 'number', 'min' => 0, 'max' => 5, 'control' => 'slider']), 'backend UI hint wins');
    }

    public function test_props_for_the_chosen_control(): void
    {
        $slot = RuleControl::describe($this->def('max_advance_days'));
        $this->assertSame(['min' => 0.0, 'max' => 730.0, 'step' => 1, 'unit' => 'days', 'ticks' => 5, 'snap' => []], $slot['props']);

        $ttl = RuleControl::describe($this->def('hold_ttl_seconds'))['props'];
        $this->assertSame('seconds', $ttl['unit']);
        $this->assertSame(['seconds', 'minutes', 'hours', 'days'], $ttl['units'], 'min is 30 s, so seconds stay available');
        $cutoff = RuleControl::describe($this->def('cancel_cutoff_minutes'))['props'];
        $this->assertSame(['minutes', 'hours', 'days'], $cutoff['units'], 'base unit minutes: never finer than the API stores');
        $this->assertTrue($ttl['slider']);

        $money = RuleControl::describe($this->def('approval_threshold_amount'))['props'];
        $this->assertSame(4, $money['scale'], 'the API sends 4-decimal strings');
        $this->assertSame('₦', $money['currency']);

        $seg = RuleControl::describe($this->def('slot_granularity_minutes'));
        $this->assertCount(6, $seg['props']['options']);
    }

    public function test_default_text_and_danger_badges(): void
    {
        $this->assertSame('Default: 10 minutes', RuleControl::describe($this->def('hold_ttl_seconds'))['defaultText']);
        $this->assertSame('Default: On', RuleControl::describe($this->def('require_cash_session'))['defaultText']);
        $this->assertSame('Default: Off', RuleControl::describe($this->def('waiter_cash_holding'))['defaultText']);
        $this->assertSame('Default: ₦50,000.00', RuleControl::describe($this->def('waiter_cash_limit'))['defaultText']);
        $this->assertSame('Default: Pay after service', RuleControl::describe($this->def('payment_timing'))['defaultText']);
        $this->assertSame('Default: none', RuleControl::describe($this->def('require_approval_for'))['defaultText']);
        $this->assertSame('Default: 20%', RuleControl::describe($this->def('booking_local_reserve_percent'))['defaultText']);
        $this->assertSame('Default: 8:00 AM', RuleControl::describe($this->def('opening_time'))['defaultText']);
        $this->assertSame('Default: not set', RuleControl::describe($this->def('max_occupancy'))['defaultText']);
        $this->assertSame('Default: 60 min', RuleControl::describe($this->def('slot_granularity_minutes'))['defaultText']);

        $badges = fn (string $k) => array_column(RuleControl::describe($this->def($k))['badges'], 'text');
        $this->assertSame(['High impact'], $badges('require_cash_session'));
        $this->assertSame(['Medium impact'], $badges('payment_timing'));
        $this->assertSame([], $badges('receipt_copies'));
        $this->assertSame(['Medium impact', 'Not enforced yet'], $badges('tab_max_amount'));
    }

    public function test_rule_field_renders_label_description_badge_default_and_reset(): void
    {
        $h = $this->render('<x-form.rule-field :def="$def" name="rules[hold_ttl_seconds]" :value="1800" />', ['def' => $this->def('hold_ttl_seconds')]);
        $this->assertStringContainsString('data-rule="hold_ttl_seconds"', $h);
        $this->assertStringContainsString('data-danger="medium"', $h);
        $this->assertStringContainsString('Hold time', $h);
        $this->assertStringContainsString('How long a slot is held', $h, 'plain-language description');
        $this->assertStringContainsString('Medium impact', $h);
        $this->assertStringContainsString('Default: 10 minutes', $h);
        $this->assertStringContainsString('Reset to default', $h);
        $this->assertStringContainsString('name="rules[hold_ttl_seconds]" value="1800"', $h);
        $this->assertStringContainsString('30 minutes', $h, 'value shown in the best unit');
    }

    public function test_rule_field_uses_the_default_when_no_value_is_given_and_shows_errors(): void
    {
        $h = $this->render('<x-form.rule-field :def="$def" name="rules[require_cash_session]" />', ['def' => $this->def('require_cash_session')], ['rules.require_cash_session' => ['Not allowed for this facility.']]);
        $this->assertStringContainsString('aria-checked="true"', $h, 'default true');
        $this->assertStringContainsString('High impact', $h);
        $this->assertStringContainsString('Not allowed for this facility.', $h);
        $this->assertStringContainsString('Turning this off removes a key cash control', $h);
    }

    public function test_schema_groups_sections_and_names_every_input(): void
    {
        $h = $this->render('<x-form.schema :definitions="$defs" name="rules" :values="$values" :options="$options" />', [
            'defs' => $this->defs(),
            'values' => ['hold_ttl_seconds' => 1800, 'approval_threshold_amount' => '15000.0000', 'payment_facility_unit_id' => 'fac-4'],
            'options' => ['payment_facility_unit_id' => ['fac-4' => 'Main Reception']],
        ]);
        foreach (['Payments &amp; cash', 'Approvals', 'Booking', 'Orders', 'Receipts', 'Capacity'] as $group) {
            $this->assertStringContainsString($group, $h);
        }
        $this->assertSame(count($this->defs()), substr_count($h, 'class="f-rule"'), 'every rule rendered once');
        $this->assertStringContainsString('name="rules[approval_threshold_amount]" value="15000.0000"', $h, 'money posted as the decimal string');
        $this->assertStringContainsString('name="rules[hold_ttl_seconds]" value="1800"', $h);
        $this->assertStringContainsString('name="rules[require_approval_for][]"', $h);
        $this->assertStringContainsString('Main Reception', $h);
        $this->assertStringContainsString('data-testid="danger-confirm"', $h);
        $this->assertStringContainsString('name="confirm"', $h);
    }

    public function test_schema_binds_wire_model_per_rule_and_filters_by_capability(): void
    {
        $h = $this->render('<x-form.schema :definitions="$defs" wire="settings" :live="true" :capabilities="[\'BOOKING\']" />', ['defs' => $this->defs()]);
        $this->assertStringContainsString('wire:model.live="settings.hold_ttl_seconds"', $h);
        $this->assertStringContainsString('wire:model="confirm"', $h, 'danger acknowledgement binds to $confirm');
        $this->assertStringNotContainsString('data-rule="receipt_copies"', $h, 'RECEIPT_PRINTING is not enabled');
        $this->assertStringNotContainsString('data-rule="payment_timing"', $h);
        $this->assertStringContainsString('data-rule="cancel_fee_percent"', $h);
    }

    public function test_reconciles_with_the_real_contract_fixture(): void
    {
        $real = json_decode((string) file_get_contents(base_path('tests/Fixtures/real/organization-rule-definitions.json')), true)['items'];
        $controls = collect($real)->mapWithKeys(fn ($d) => [$d['key'] => RuleControl::describe($d)['control']])->all();
        $this->assertSame('radio-cards', $controls['payment_timing'], '6 described options read best as cards');
        $this->assertSame('radio-cards', $controls['booking_offline_strategy']);
        $this->assertSame('toggle', $controls['waiter_cash_holding']);
        $this->assertSame('money', $controls['waiter_cash_in_hand_limit']);
        $this->assertSame('checkbox-group', $controls['collection_requires_confirmation']);
        $this->assertSame('duration', $controls['hold_ttl_seconds']);
        $this->assertSame('percent', $controls['cancel_fee_percent']);
        $this->assertCount(count($real), $controls);
    }
}
