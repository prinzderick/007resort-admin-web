<?php

namespace Tests\Feature;

use App\Livewire\FormsDemo;
use Livewire\Livewire;
use Tests\TestCase;

/** The controls bound with wire:model in a real Livewire component: sync, validation display, decimal-string safety. */
class FormLivewireTest extends TestCase
{
    public function test_controls_render_with_their_wire_model_bindings(): void
    {
        $html = Livewire::test(FormsDemo::class)->html();
        foreach (['wire:model="settings.hold_ttl_seconds"', 'wire:model.live="settings.cancel_fee_percent"', 'wire:model="settings.payment_timing"', 'wire:model="settings.require_cash_session"',
            'wire:model="price"', 'wire:model="band"', 'wire:model="seats"', 'wire:model="confirm"'] as $binding) {
            $this->assertStringContainsString($binding, $html, $binding);
        }
        $this->assertStringContainsString('x-modelable="value"', $html);
    }

    public function test_initial_state_is_pushed_into_the_controls(): void
    {
        Livewire::test(FormsDemo::class)
            ->assertSet('settings.hold_ttl_seconds', 600)
            ->assertSet('price', '2500.00')
            ->assertSee('10 minutes')            // 600 s shown as the best unit
            ->assertSee('Default: 10 minutes');
    }

    public function test_money_stays_a_decimal_string_through_updates_and_saves(): void
    {
        $c = Livewire::test(FormsDemo::class)
            ->set('price', '1500.5')
            ->assertSet('price', '1500.5');
        $this->assertIsString($c->get('price'));

        $c->set('price', '9007199254740993.99')->call('save')->assertHasErrors('price'); // above the limit, still exact
        $this->assertSame('9007199254740993.99', $c->get('price'), 'no float round trip');

        $c->set('price', '2500.0000')->call('save')->assertHasNoErrors();
        $this->assertSame('2500.0000', $c->get('price'));
        $this->assertIsString($c->get('settings')['approval_threshold_amount']);
    }

    public function test_validation_errors_show_on_the_controls(): void
    {
        Livewire::test(FormsDemo::class)
            ->set('settings.hold_ttl_seconds', 5)
            ->set('price', '2000000')
            ->call('save')
            ->assertHasErrors(['settings.hold_ttl_seconds' => 'between', 'price'])
            ->assertSee('must be between 30 and 86400')
            ->assertSee('Prices above ₦1,000,000')
            ->assertSeeHtml('data-invalid="true"')
            ->assertSeeHtml('role="alert"');
    }

    public function test_range_gap_and_stepper_bounds_are_validated_server_side_too(): void
    {
        Livewire::test(FormsDemo::class)
            ->set('band', ['from' => 60, 'to' => 20])
            ->set('seats', 40)
            ->call('save')
            ->assertHasErrors(['band.to' => 'gte', 'seats' => 'between']);
    }

    public function test_saving_dispatches_form_saved_and_confirms_the_high_impact_change(): void
    {
        $c = Livewire::test(FormsDemo::class)
            ->set('settings.require_cash_session', false)
            ->call('save')
            ->assertHasErrors('confirm')
            ->assertNotDispatched('form-saved')
            ->assertSee('Tick the confirmation');

        $c->set('confirm', true)->call('save')
            ->assertHasNoErrors()
            ->assertDispatched('form-saved')
            ->assertSee('Saved.');
    }

    public function test_slider_and_number_box_share_one_model(): void
    {
        // The slider thumb, the bubble and the number box all read/write the same Alpine `value` (JS-side sync is unit-tested in tests/js);
        // here we assert the wiring is one-model: the number input is x-model="text", synced from `value`, and the root carries wire:model.
        $html = Livewire::test(FormsDemo::class)->html();
        $this->assertStringContainsString('wire:model.live="settings.cancel_fee_percent"', $html);
        $this->assertStringContainsString('x-data="fSlider(', $html);
        $this->assertStringContainsString('x-on:input="typeIn()"', $html);
        $this->assertStringContainsString('x-on:blur="commitText()"', $html);
        $this->assertStringContainsString('role="slider"', $html);
    }

    public function test_the_styleguide_page_and_demo_are_dev_only(): void
    {
        $this->get('/styleguide/forms')->assertOk()->assertSee('Form controls');
        // Route registration is decided at boot; assert the guard exists in the route file.
        $this->assertStringContainsString("app()->environment(['local', 'testing'])", (string) file_get_contents(base_path('routes/web.php')));
    }
}
