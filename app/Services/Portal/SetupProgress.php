<?php

namespace App\Services\Portal;

use App\Auth\StaffSession;
use App\Services\R007Api\R007ApiClient;
use App\Support\Contract;
use App\Support\Fetch;

/**
 * How far the property is set up. Uses the API's GET /admin/setup-status when the contract has it; until then it is worked
 * out from what the signed-in account can read, so the onboarding card is honest either way.
 */
class SetupProgress
{
    public function __construct(private readonly R007ApiClient $api, private readonly StaffSession $staff, private readonly DashboardData $dash) {}

    /** @return array{steps: list<array<string, mixed>>, percent: int, derived: bool} */
    public function get(): array
    {
        if (Contract::has('GET', '/admin/setup-status')) {
            $r = Fetch::of(fn () => $this->api->get('admin/setup-status'), ['GET', '/admin/setup-status']);
            if ($r->ok() && is_array($r->data) && isset($r->data['steps'])) {
                return ['steps' => (array) $r->data['steps'], 'percent' => (int) ($r->data['percent'] ?? 0), 'derived' => false];
            }
        }

        $tree = Fetch::of(fn () => $this->api->get('organization/facilities'), ['GET', '/organization/facilities']);
        $steps = [];
        $steps[] = ['key' => 'facilities', 'label' => 'Add your facilities', 'done' => count($this->dash->flatten($tree->items())) > 0, 'route' => 'setup.facilities', 'hint' => 'Restaurants, bars, pools, courts, spa, stores.'];
        $cats = Fetch::of(fn () => $this->api->get('catalog/categories', ['limit' => 1]), ['GET', '/catalog/categories']);
        $steps[] = ['key' => 'catalog', 'label' => 'Set up the catalog and prices', 'done' => count($cats->items()) > 0, 'route' => 'setup.catalog', 'hint' => 'Categories, products, prices and tax.'];
        $res = Fetch::of(fn () => $this->api->get('bookings/resources', ['limit' => 1]), ['GET', '/bookings/resources']);
        $steps[] = ['key' => 'booking', 'label' => 'Add bookable resources', 'done' => count($res->items()) > 0, 'route' => 'setup.bookings', 'hint' => 'Courts, pitches, salon chairs, halls.'];
        $plans = Fetch::of(fn () => $this->api->get('memberships/plans', ['limit' => 1]), ['GET', '/memberships/plans']);
        $steps[] = ['key' => 'plans', 'label' => 'Create membership plans', 'done' => count($plans->items()) > 0, 'route' => 'setup.memberships', 'hint' => 'Gold, Silver, pool pass...'];
        if ($this->staff->can('staff.manage')) {
            $st = Fetch::of(fn () => $this->api->get('staff', ['limit' => 2]), ['GET', '/staff']);
            $steps[] = ['key' => 'staff', 'label' => 'Add staff and give them roles', 'done' => count($st->items()) > 1, 'route' => 'staff.index', 'hint' => 'Each person gets a role at a scope.'];
        }
        if ($this->staff->canAny('device.register', 'device.view')) {
            $dv = Fetch::of(fn () => $this->api->get('devices', ['limit' => 1]), ['GET', '/devices']);
            $steps[] = ['key' => 'devices', 'label' => 'Register tablets, POS and KDS screens', 'done' => count($dv->items()) > 0, 'route' => 'devices.index', 'hint' => 'Issue a one-time code, enter it on the device.'];
        }
        $tax = Fetch::of(fn () => $this->api->get('admin/settings/tax'), ['GET', '/admin/settings/tax']);
        $steps[] = ['key' => 'tax', 'label' => 'Decide on VAT', 'done' => $tax->ok() && (int) ($tax->data['rowVersion'] ?? 0) > 0, 'route' => 'setup.business', 'hint' => 'VAT is off until you switch it on.'];
        $done = count(array_filter($steps, fn ($x) => $x['done']));

        return ['steps' => $steps, 'percent' => (int) round($done / max(1, count($steps)) * 100), 'derived' => true];
    }
}
