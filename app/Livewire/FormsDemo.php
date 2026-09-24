<?php

namespace App\Livewire;

use Livewire\Component;

/**
 * Development-only demo (styleguide /styleguide/forms): the form-control library bound with wire:model, with server-side validation
 * errors showing on the controls exactly as the API's 422 messages will. Never routed in production.
 */
class FormsDemo extends Component
{
    /** @var array<string, mixed> */
    public array $settings = [
        'hold_ttl_seconds' => 600,
        'cancel_fee_percent' => 10,
        'approval_threshold_amount' => '10000.0000',
        'payment_timing' => 'PAY_AFTER_SERVICE',
        'require_cash_session' => true,
    ];

    public string $price = '2500.00';

    public array $band = ['from' => 20, 'to' => 60];

    public int $seats = 4;

    public bool $confirm = false;

    public ?string $saved = null;

    public function save(): void
    {
        $this->saved = null;
        $this->validate([
            'settings.hold_ttl_seconds' => ['required', 'integer', 'between:30,86400'],
            'settings.cancel_fee_percent' => ['required', 'numeric', 'between:0,100'],
            'settings.approval_threshold_amount' => ['required', 'regex:/^\d+(\.\d{1,4})?$/'],
            'settings.payment_timing' => ['required', 'in:PAY_AFTER_SERVICE,PAY_FIRST,OPEN_TAB'],
            'price' => ['required', 'regex:/^\d+(\.\d{1,4})?$/', function (string $attr, mixed $v, \Closure $fail): void {
                if (bccomp((string) $v, '1000000', 4) > 0) {
                    $fail('Prices above ₦1,000,000 need the owner to raise the limit.');
                }
            }],
            'band.from' => ['required', 'integer', 'min:0'],
            'band.to' => ['required', 'integer', 'gte:band.from'],
            'seats' => ['required', 'integer', 'between:1,20'],
        ]);
        if (($this->settings['require_cash_session'] ?? true) === false && ! $this->confirm) {
            $this->addError('confirm', 'Tick the confirmation to switch off the cash-session control.');

            return;
        }
        $this->saved = 'Saved. price='.var_export($this->price, true).' (a string), hold='.$this->settings['hold_ttl_seconds'].'s';
        $this->dispatch('form-saved');
    }

    public function render()
    {
        return view('styleguide.forms-demo', ['definitions' => collect(json_decode((string) file_get_contents(resource_path('fixtures/rule-definitions.json')), true)['items'])->whereIn('key', array_keys($this->settings))->values()->all()]);
    }
}
