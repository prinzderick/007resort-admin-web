<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Blade;
use Illuminate\Support\MessageBag;
use Illuminate\Support\ViewErrorBag;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/** Render contract of the x-form.* control library: shell (label, help, error, required), states, model bindings, posted values. */
class FormComponentsTest extends TestCase
{
    private function render(string $blade, array $errors = []): string
    {
        view()->share('errors', (new ViewErrorBag)->put('default', new MessageBag($errors)));

        return Blade::render($blade);
    }

    /** The JSON config handed to the Alpine component: x-data="fSomething(JSON.parse('{...}'))". */
    private function cfg(string $html): array
    {
        $this->assertSame(1, preg_match("/JSON\\.parse\\((?:&#039;|')(.*?)(?:&#039;|')\\)\\)/s", $html, $m), 'x-data config');

        // What the browser's JS string literal does to the payload before JSON.parse sees it.
        $json = preg_replace_callback('/\\\\(u0022|\\\\|\'|\/)/', fn ($x) => match ($x[1]) {
            'u0022' => '"', '\\' => '\\', "'" => "'", '/' => '/',
        }, $m[1]);

        return json_decode((string) $json, true, 512, JSON_THROW_ON_ERROR);
    }

    private function cfgText(string $html): string
    {
        preg_match('/x-data="([^"]*)"/', $html, $m);

        return $m[1] ?? '';
    }

    /** Every control: [component tag with the props that make it valid]. */
    public static function controls(): array
    {
        return [
            'toggle' => ['<x-form.toggle name="f" label="L" :value="true" />', 'toggle'],
            'slider' => ['<x-form.slider name="f" label="L" :value="30" :min="5" :max="120" :step="5" />', 'slider'],
            'range' => ['<x-form.range name="f" label="L" :value="[\'from\' => 10, \'to\' => 40]" />', 'range'],
            'stepper' => ['<x-form.stepper name="f" label="L" :value="2" :min="1" :max="5" />', 'stepper'],
            'percent' => ['<x-form.percent name="f" label="L" :value="25" />', 'slider'],
            'segmented' => ['<x-form.segmented name="f" label="L" :options="[\'a\' => \'A\', \'b\' => \'B\']" value="a" />', 'segmented'],
            'radio-cards' => ['<x-form.radio-cards name="f" label="L" :options="[[\'value\' => \'a\', \'label\' => \'A\', \'description\' => \'d\']]" value="a" />', 'radio-cards'],
            'checkbox-group' => ['<x-form.checkbox-group name="f" label="L" :options="[\'a\' => \'A\', \'b\' => \'B\']" :value="[\'a\']" />', 'checkbox-group'],
            'money' => ['<x-form.money name="f" label="L" value="1500.50" />', 'money'],
            'duration' => ['<x-form.duration name="f" label="L" unit="seconds" :value="600" />', 'duration'],
            'time' => ['<x-form.time name="f" label="L" value="08:00" />', 'time'],
            'time-range' => ['<x-form.time-range name="f" label="L" :value="[\'from\' => \'08:00\', \'to\' => \'17:00\']" />', 'time-range'],
            'weekly-hours' => ['<x-form.weekly-hours name="f" label="L" />', 'weekly-hours'],
            'select' => ['<x-form.select name="f" label="L" :options="[\'a\' => \'A\']" value="a" />', 'select'],
            'date' => ['<x-form.date name="f" label="L" value="2026-09-24" />', 'date'],
            'date-range' => ['<x-form.date-range name="f" label="L" :value="[\'from\' => \'2026-09-20\', \'to\' => \'2026-09-26\']" />', 'date-range'],
            'text' => ['<x-form.text name="f" label="L" value="x" />', 'text'],
            'textarea' => ['<x-form.textarea name="f" label="L" value="x" :maxlength="50" />', 'textarea'],
            'password' => ['<x-form.password name="f" label="L" />', 'password'],
            'pin' => ['<x-form.pin name="f" label="L" />', 'pin'],
            'color' => ['<x-form.color name="f" label="L" value="#0f7d4f" />', 'color'],
            'file' => ['<x-form.file name="f" label="L" />', 'file'],
            'image' => ['<x-form.image name="f" label="L" />', 'image'],
            'tags' => ['<x-form.tags name="f" label="L" :value="[\'a\']" />', 'tags'],
            'key-value' => ['<x-form.key-value name="f" label="L" :value="[\'k\' => \'v\']" />', 'key-value'],
        ];
    }

    /** @dataProvider controls */
    #[DataProvider('controls')]
    public function test_every_control_renders_the_shared_shell(string $tag, string $type): void
    {
        $h = $this->render($tag);
        $this->assertStringContainsString('data-f="'.$type.'"', $h, 'shell type');
        $this->assertStringContainsString($type === 'toggle' ? 'f-toggle-title' : 'class="f-label"', $h, 'label');
        $this->assertStringContainsString('x-data="f', $h, 'alpine component');
        $this->assertStringContainsString('data-invalid="false"', $h);
        $this->assertStringContainsString('x-show="$data.dirty"', $h, 'dirty indicator');
    }

    #[DataProvider('controls')]
    public function test_every_control_shows_a_server_error_and_wires_aria(string $tag, string $type): void
    {
        $h = $this->render($tag, ['f' => ['The f field must be valid.'], 'f.from' => ['Bad start.']]);
        $this->assertStringContainsString('data-invalid="true"', $h);
        $this->assertStringContainsString('role="alert"', $h);
        $this->assertStringContainsString('The f field must be valid.', $h);
        $this->assertStringContainsString('-error', $h, 'error id for aria-describedby');
    }

    #[DataProvider('controls')]
    public function test_every_control_forwards_wire_model_to_the_alpine_root(string $tag, string $type): void
    {
        if (in_array($type, ['file', 'image'], true)) {
            $this->markTestSkipped('File inputs take wire:model on the <input type=file> itself (checked separately).');
        }
        $h = $this->render(str_replace(' name="f"', ' wire:model.live="form.f"', $tag));
        $this->assertStringContainsString('wire:model.live="form.f"', $h);
        $this->assertStringContainsString('x-modelable="value"', $h, 'x-modelable lets wire:model / x-model bind the control');
    }

    public function test_required_optional_hint_and_disabled_states(): void
    {
        $h = $this->render('<x-form.text name="n" label="Name" :required="true" hint="Shown on receipts." />');
        $this->assertStringContainsString('class="f-req"', $h);
        $this->assertStringContainsString('(required)', $h);
        $this->assertStringContainsString('aria-required="true"', $h);
        $this->assertStringContainsString('Shown on receipts.', $h);
        $this->assertStringContainsString('aria-describedby="f-n-hint"', $h);

        $this->assertStringContainsString('Optional', $this->render('<x-form.text name="n" label="Name" :optional="true" />'));
        $d = $this->render('<x-form.text name="n" label="Name" :disabled="true" />');
        $this->assertStringContainsString('data-disabled="true"', $d);
        $this->assertStringNotContainsString('disabled', html_entity_decode($this->cfgText($d)), 'volatile state stays out of the x-data text');
    }

    public function test_x_data_text_is_stable_when_a_model_owns_the_value(): void
    {
        // Alpine re-initialises (and wipes) a component whose x-data text changes on a Livewire re-render, so the value and error
        // state must never be part of it when wire:model / x-model owns the value.
        $a = $this->render('<x-form.money wire:model="price" label="P" value="100.00" />');
        $b = $this->render('<x-form.money wire:model="price" label="P" value="999.00" />', ['price' => ['Too big.']]);
        $this->assertSame($this->cfgText($a), $this->cfgText($b));
        $this->assertNull($this->cfg($a)['value']);
        // ...but a plain (unbound) control carries its initial value.
        $this->assertSame('100.00', $this->cfg($this->render('<x-form.money name="price" label="P" value="100.00" />'))['value']);
    }

    public function test_error_message_comes_from_dotted_error_key_for_bracket_names(): void
    {
        $h = $this->render('<x-form.text name="contact[phone]" label="Phone" />', ['contact.phone' => ['Phone is invalid.']]);
        $this->assertStringContainsString('Phone is invalid.', $h);
        $this->assertStringContainsString('name="contact[phone]"', $h);
    }

    public function test_toggle_posts_1_or_0_and_exposes_switch_semantics(): void
    {
        $on = $this->render('<x-form.toggle name="open_tabs" label="Allow tabs" description="Pay later." :value="true" on-text="Allowed" off-text="Blocked" />');
        $this->assertStringContainsString('role="switch"', $on);
        $this->assertStringContainsString('aria-checked="true"', $on);
        $this->assertStringContainsString('<input type="hidden" name="open_tabs" value="1"', $on);
        $this->assertStringContainsString('Pay later.', $on);
        $this->assertStringContainsString('Allowed', $on);
        $off = $this->render('<x-form.toggle name="open_tabs" label="Allow tabs" :value="false" />');
        $this->assertStringContainsString('aria-checked="false"', $off);
        $this->assertStringContainsString('name="open_tabs" value="0"', $off);
    }

    public function test_slider_exposes_slider_aria_number_box_and_config(): void
    {
        $h = $this->render('<x-form.slider name="hold" label="Hold" :value="30" :min="5" :max="120" :step="5" unit=" min" :ticks="5" :snap="[15, 30, 60]" />');
        $this->assertStringContainsString('role="slider"', $h);
        $this->assertStringContainsString('aria-orientation="horizontal"', $h);
        $this->assertStringContainsString('inputmode="decimal"', $h, 'editable number box');
        $this->assertStringContainsString('name="hold"', $h);
        $cfg = $this->cfg($h);
        $this->assertSame(5, $cfg['min']);
        $this->assertSame([15, 30, 60], $cfg['snap']);
        $this->assertSame(30, $cfg['value']);
    }

    public function test_range_posts_from_and_to_and_supports_gap_config(): void
    {
        $h = $this->render('<x-form.range name="band" label="Band" :value="[\'from\' => 10, \'to\' => 40]" :min-gap="5" />');
        $this->assertStringContainsString('name="band[from]"', $h);
        $this->assertStringContainsString('name="band[to]"', $h);
        $this->assertSame(2, substr_count($h, 'role="slider"'), 'two handles');
        $this->assertSame(5, $this->cfg($h)['minGap']);
    }

    public function test_money_keeps_a_decimal_string_and_formats_only_the_display(): void
    {
        $h = $this->render('<x-form.money name="cap" label="Cap" value="1234567.5000" :scale="4" />');
        $this->assertStringContainsString('value="1,234,567.50"', $h, 'display has separators and 2 decimals');
        $this->assertStringContainsString('name="cap" value="1234567.5000"', $h, 'posted value is the raw decimal string');
        $this->assertStringContainsString('data-money="decimal-string"', $h);
        $this->assertStringContainsString('₦', $h);
        // a value beyond 2^53 is never coerced through a float
        $big = $this->render('<x-form.money name="cap" label="Cap" value="9007199254740993.99" />');
        $this->assertStringContainsString('name="cap" value="9007199254740993.99"', $big);
        $this->assertStringContainsString('9,007,199,254,740,993.99', $big);
    }

    public function test_duration_shows_the_best_unit_and_human_readout(): void
    {
        $h = $this->render('<x-form.duration name="ttl" label="TTL" unit="seconds" :value="7200" :units="[\'minutes\', \'hours\', \'days\']" />');
        $this->assertStringContainsString('value="2"', $h, '7200 seconds shown as 2 (hours)');
        $this->assertStringContainsString('2 hours', $h);
        $this->assertStringContainsString('name="ttl" value="7200"', $h, 'posted in the API unit');
    }

    public function test_choice_controls_use_native_inputs_and_mark_the_selection(): void
    {
        $seg = $this->render('<x-form.segmented name="slot" label="Slot" :options="[\'15\' => \'15 min\', \'30\' => \'30 min\']" value="30" />');
        $this->assertStringContainsString('role="radiogroup"', $seg);
        $this->assertStringContainsString('type="radio" name="slot" value="30"', $seg);
        $this->assertMatchesRegularExpression('/data-on="true"[^>]*>\s*<input type="radio" name="slot" value="30"/', $seg);

        $cards = $this->render('<x-form.radio-cards name="timing" label="Timing" :options="[[\'value\' => \'A\', \'label\' => \'Pay first\', \'description\' => \'Before prep\', \'icon\' => \'bolt\', \'badge\' => \'Recommended\']]" value="A" />');
        $this->assertStringContainsString('Before prep', $cards);
        $this->assertStringContainsString('Recommended', $cards);
        $this->assertStringContainsString('f-card-icon', $cards);

        $chk = $this->render('<x-form.checkbox-group name="ops" label="Ops" :options="[\'void\' => \'Void\', \'refund\' => \'Refund\']" :value="[\'refund\']" :select-all="true" />');
        $this->assertStringContainsString('type="checkbox" name="ops[]" value="refund"', $chk);
        $this->assertMatchesRegularExpression('/data-on="true"[^>]*>\s*<input type="checkbox" name="ops\[\]" value="refund"/', $chk);
        $this->assertStringContainsString('Select all', $chk);
    }

    public function test_select_renders_combobox_semantics_and_hidden_multi_inputs(): void
    {
        $single = $this->render('<x-form.select name="city" label="City" :options="[\'lagos\' => \'Lagos\', \'abuja\' => \'Abuja\']" value="lagos" />');
        $this->assertStringContainsString('role="combobox"', $single);
        $this->assertStringContainsString('role="listbox"', $single);
        $this->assertStringContainsString('name="city" value="lagos"', $single);
        $this->assertStringContainsString('>Lagos</span>', $single, 'selected label is server-rendered');

        $multi = $this->render('<x-form.select name="cities" label="Cities" :multiple="true" :options="[\'a\' => \'A\']" :value="[\'a\']" :creatable="true" />');
        $this->assertStringContainsString('aria-multiselectable="true"', $multi);
        $this->assertStringContainsString("'cities[]'", $multi);
        $this->assertTrue($this->cfg($multi)['creatable']);
    }

    public function test_text_has_counter_prefix_suffix_and_clear(): void
    {
        $h = $this->render('<x-form.text name="site" label="Site" value="abc" prefix="https://" suffix=".ng" :clearable="true" :maxlength="10" :counter="true" />');
        $this->assertStringContainsString('https://', $h);
        $this->assertStringContainsString('.ng', $h);
        $this->assertStringContainsString('aria-label="Clear"', $h);
        $this->assertStringContainsString('/ 10', $h);
    }

    public function test_password_never_echoes_a_value_and_has_a_toggle(): void
    {
        $h = $this->render('<x-form.password name="pw" label="Password" value="secret123" :meter="true" />');
        $this->assertStringNotContainsString('secret123', $h);
        $this->assertStringContainsString('Show password', $h);
        $this->assertStringContainsString('f-strength', $h);
    }

    public function test_pin_renders_one_box_per_digit_and_an_optional_keypad(): void
    {
        $h = $this->render('<x-form.pin name="pin" label="PIN" :length="6" :pad="true" />');
        $this->assertSame(6, substr_count($h, 'aria-label="Digit '));
        $this->assertStringContainsString('autocomplete="one-time-code"', $h);
        $this->assertStringContainsString('PIN keypad', $h);
    }

    public function test_file_declares_limits_and_puts_wire_model_on_the_file_input(): void
    {
        $h = $this->render('<x-form.file label="Invoice" wire:model="upload" accept=".pdf,image/*" :max-size="5" :multiple="true" />');
        $this->assertMatchesRegularExpression('/<input[^>]*type="file"[^>]*wire:model="upload"/', $h);
        $this->assertStringContainsString('accept=".pdf,image/*"', $h);
        $this->assertStringContainsString('up to 5 MB', $h);
        $this->assertSame(5242880, $this->cfg($h)['maxBytes']);
        $this->assertStringContainsString('x-on:change.capture="onChange', $h, 'validates before Livewire uploads');
    }

    public function test_weekly_hours_posts_json_and_lists_every_day(): void
    {
        $h = $this->render('<x-form.weekly-hours name="hours" label="Hours" />');
        $this->assertStringContainsString('name="hours"', $h);
        $this->assertStringContainsString('JSON.stringify(value)', $h);
        $this->assertStringContainsString('Exceptions and holidays', $h);
        $this->assertStringContainsString('Copy to all weekdays', $h);
    }

    public function test_actions_bar_has_save_cancel_and_status(): void
    {
        $h = $this->render('<x-form.actions submit="Save rules" />');
        $this->assertStringContainsString('x-data="fActions', $h);
        $this->assertStringContainsString('Save rules', $h);
        $this->assertStringContainsString('All changes saved', $h);
        $this->assertStringContainsString('type="submit"', $h);
        $this->assertStringContainsString(':disabled="busy"', $h, 'double submit is blocked while saving');
        $this->assertStringContainsString('Discard', $h);
    }

    public function test_confirm_requires_the_typed_phrase(): void
    {
        $h = $this->render('<x-form.confirm phrase="DISABLE PAYMENTS" title="Sure?" message="Cashiers stop."><x-slot:trigger><button type="button">Go</button></x-slot:trigger></x-form.confirm>');
        $this->assertStringContainsString('DISABLE PAYMENTS', $h);
        $this->assertStringContainsString('role="alertdialog"', $h);
        $this->assertStringContainsString(':disabled="!matches"', $h);
        $this->assertStringContainsString('Cashiers stop.', $h);
    }

    public function test_section_renders_title_description_and_body(): void
    {
        $h = $this->render('<x-form.section title="Payments" description="How cash works."><p>BODY</p></x-form.section>');
        $this->assertStringContainsString('Payments', $h);
        $this->assertStringContainsString('How cash works.', $h);
        $this->assertStringContainsString('BODY', $h);
    }

    public function test_touch_target_and_focus_tokens_are_defined_in_the_stylesheet(): void
    {
        $css = (string) file_get_contents(resource_path('css/form.css'));
        $this->assertStringContainsString('--f-h: 2.75rem', $css, '44px touch target');
        $this->assertStringContainsString('box-shadow: 0 0 0 3px var(--f-ring)', $css, 'visible focus ring');
        $this->assertStringContainsString('prefers-reduced-motion', $css);
    }

    public function test_every_component_appears_in_the_styleguide(): void
    {
        $html = $this->get('/styleguide/forms')->assertOk()->getContent();
        $skip = ['field', 'aside', 'icon', 'section', 'schema', 'rule-field', 'actions', 'confirm', 'percent'];
        foreach (glob(resource_path('views/components/form/*.blade.php')) as $file) {
            $name = basename($file, '.blade.php');
            if (str_starts_with($name, '_') || in_array($name, $skip, true)) {
                continue;
            }
            $anchor = $name === 'weekly-hours' ? 'id="hours"' : 'id="c-'.$name.'"';
            $this->assertStringContainsString($anchor, $html, "x-form.$name is missing from /styleguide/forms");
        }
        foreach (['data-component="rule-schema"', 'data-component="save-bar"', 'f-confirm-box', 'data-testid="livewire-demo"', 'x-form.percent'] as $needle) {
            $this->assertStringContainsString($needle, $html);
        }
    }
}
