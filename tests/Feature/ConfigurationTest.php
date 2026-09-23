<?php

namespace Tests\Feature;

use App\Support\Contract;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\Support\Fixtures as F;
use Tests\TestCase;

class ConfigurationTest extends TestCase
{
    private const ADMIN = ['config.manage', 'facility.configure', 'pricing.manage', 'membership.plan.manage', 'catalog.availability.manage'];

    private function api(array $o = []): void
    {
        $this->fakeApi($o + [
            'GET /organization/facilities' => F::facilities(),
            'GET /facilities/*/capabilities' => ['facilityId' => F::FAC, 'capabilities' => ['TABLE_SERVICE', 'KDS'], 'operatingRules' => ['approvalThresholdAmount' => '5000.0000', 'requireApprovalFor' => ['VOID'], 'allowOpenTabs' => true, 'requireCashSession' => true, 'allowOfflineOrders' => true, 'allowOfflinePayments' => 'CASH_ONLY', 'holdTtlSeconds' => 600, 'vatEnabled' => false, 'vatRatePercent' => '7.5']],
            'GET /organization/facilities/*/operating-points' => F::page([['id' => 'op', 'facilityId' => F::FAC, 'code' => 'FLOOR', 'name' => 'Main floor', 'kind' => 'TABLE_AREA']]),
            'GET /catalog/categories' => F::page([['id' => 'c1', 'name' => 'Food']]),
            'GET /catalog/products' => F::page([['id' => 'p1', 'sku' => 'P-1', 'name' => 'Jollof Rice', 'categoryId' => 'c1', 'kind' => 'FOOD', 'price' => '3500.0000', 'currency' => 'NGN', 'taxInclusive' => true, 'taxRatePercent' => '0', 'prepRoute' => ['stationName' => 'Kitchen', 'kind' => 'KITCHEN']]]),
            'GET /catalog/availability' => F::page([['productId' => 'p1', 'facilityId' => F::FAC, 'available' => true]]),
            'GET /admin/settings/tax' => [200, ['vatEnabled' => false, 'vatRatePercent' => '7.5', 'pricesTaxInclusive' => true, 'vatNumber' => null, 'rowVersion' => 4], ['ETag' => '"4"']],
            'GET /memberships/plans' => F::page([['id' => 'pl1', 'name' => 'Monthly Gym', 'durationDays' => 30, 'price' => '25000.0000', 'facilityIds' => [], 'visitLimit' => null, 'active' => true]]),
            'GET /bookings/resources' => F::page([['id' => 'res1', 'facilityId' => F::FAC, 'name' => 'Tennis Court 1', 'mode' => 'TIME_SLOT', 'capacity' => 1, 'slotMinutes' => 60, 'price' => '8000.0000']]),
            'GET /kds/stations' => F::page([['id' => 'st1', 'facilityId' => F::FAC, 'name' => 'Kitchen', 'kind' => 'KITCHEN', 'active' => true]]),
        ]);
    }

    public function test_overview_only_lists_permitted_sections(): void
    {
        $this->api();
        $this->signIn(['membership.plan.manage'])->get('/config')->assertOk()->assertSee('Membership plans')->assertDontSee('Tax / VAT (ADR-0011)')->assertDontSee('KDS routing');
    }

    public function test_facility_tree_capabilities_rules_and_operating_points(): void
    {
        $this->api();
        $this->signIn(self::ADMIN)->get('/config/facilities?facility='.F::FAC)->assertOk()
            ->assertSee('Main Restaurant')->assertSee('TABLE_SERVICE')->assertSee('₦5,000.00')->assertSee('CASH_ONLY')->assertSee('Main floor')->assertSee('Editing rules is not in the API contract yet');
    }

    public function test_rules_edit_form_appears_only_when_the_contract_has_the_endpoint(): void
    {
        $this->api();
        $this->signIn(self::ADMIN)->get('/config/facilities?facility='.F::FAC)->assertDontSee('Save rules');
        $this->put('/config/facilities/'.F::FAC.'/rules', [])->assertNotFound();

        Contract::fake(['PUT /facilities/{facilityId}/capabilities']);
        $this->api(['PUT /facilities/*/capabilities' => [200, ['facilityId' => F::FAC]]]);
        $this->get('/config/facilities?facility='.F::FAC)->assertSee('Save rules')->assertSee('Payment timing');
        $this->put('/config/facilities/'.F::FAC.'/rules', ['approvalThresholdAmount' => '7500', 'allowOfflinePayments' => 'ALL', 'paymentTiming' => 'PAY_ON_EXIT', 'allowOpenTabs' => '1'])->assertSessionHas('success', 'Operating rules saved.');
        Http::assertSent(fn (Request $r) => $r->method() === 'PUT' && $r['operatingRules']['paymentTiming'] === 'PAY_ON_EXIT' && $r['operatingRules']['allowOpenTabs'] === true && $r['operatingRules']['requireCashSession'] === false);
    }

    public function test_catalog_shows_prices_and_toggles_availability(): void
    {
        $this->api(['PUT /catalog/products/*/availability/*' => ['productId' => 'p1', 'facilityId' => F::FAC, 'available' => false]]);
        $this->signIn(self::ADMIN)->get('/config/catalog')->assertOk()->assertSee('Jollof Rice')->assertSee('₦3,500.00')->assertSee('Kitchen')->assertSee('Mark unavailable')->assertSee('Create / edit products and change prices');

        $this->put('/config/catalog/p1/availability', ['facilityId' => F::FAC, 'available' => '0', 'reason' => 'Out of chicken'])->assertSessionHas('success', fn ($m) => str_contains($m, '86'));
        Http::assertSent(fn (Request $r) => $r->method() === 'PUT' && str_contains($r->url(), '/catalog/products/p1/availability/'.F::FAC) && $r['available'] === false && $r['reason'] === 'Out of chicken');
    }

    public function test_availability_needs_its_permission(): void
    {
        $this->api();
        $this->signIn(['pricing.manage'])->put('/config/catalog/p1/availability', ['facilityId' => F::FAC, 'available' => '0'])->assertForbidden();
    }

    public function test_tax_setting_defaults_off_and_is_saved_with_if_match(): void
    {
        $this->api(['PUT /admin/settings/tax' => ['vatEnabled' => true, 'vatRatePercent' => '7.5', 'pricesTaxInclusive' => true, 'vatNumber' => '12345678-0001', 'rowVersion' => 5]]);
        $this->signIn(self::ADMIN)->get('/config/tax')->assertOk()->assertSee('VAT-registered')->assertSee('value="7.5"', false)->assertSee('name="etag" value="&quot;4&quot;"', false);

        $this->put('/config/tax', ['vatEnabled' => '1', 'vatRatePercent' => '7.5', 'pricesTaxInclusive' => '1', 'vatNumber' => '12345678-0001', 'etag' => '"4"'])->assertRedirect('/config/tax')->assertSessionHas('success', fn ($m) => str_contains($m, 'VAT is now ON'));
        Http::assertSent(fn (Request $r) => $r->method() === 'PUT' && str_ends_with($r->url(), '/admin/settings/tax') && $r->hasHeader('If-Match', '"4"') && $r['vatEnabled'] === true && $r['vatRatePercent'] === '7.5' && $r['vatNumber'] === '12345678-0001');
    }

    public function test_switching_vat_off_is_reported_and_sends_false(): void
    {
        $this->api(['PUT /admin/settings/tax' => ['vatEnabled' => false, 'vatRatePercent' => '7.5', 'pricesTaxInclusive' => true, 'rowVersion' => 6]]);
        $this->signIn(self::ADMIN)->put('/config/tax', ['vatRatePercent' => '7.5', 'etag' => '"4"'])->assertSessionHas('success', fn ($m) => str_contains($m, 'VAT is OFF'));
        Http::assertSent(fn (Request $r) => $r->method() === 'PUT' && $r['vatEnabled'] === false);
    }

    public function test_tax_rate_is_validated(): void
    {
        $this->api();
        $this->signIn(self::ADMIN)->put('/config/tax', ['vatRatePercent' => '150'])->assertSessionHasErrors('vatRatePercent');
        Http::assertNothingSent();
    }

    public function test_tax_stale_version_conflict_is_shown(): void
    {
        $this->api(['PUT /admin/settings/tax' => $this->problem(412, 'concurrency_conflict', 'The setting changed since you opened it.')]);
        $this->signIn(self::ADMIN)->from('/config/tax')->put('/config/tax', ['vatRatePercent' => '7.5', 'etag' => '"1"'])->assertSessionHas('error', fn ($m) => str_contains($m, 'changed since you opened it'));
    }

    public function test_membership_plan_create_and_edit(): void
    {
        $this->api(['POST /memberships/plans' => [201, ['id' => 'new']], 'PATCH /memberships/plans/*' => ['id' => 'pl1']]);
        $this->signIn(self::ADMIN)->get('/config/memberships')->assertOk()->assertSee('Monthly Gym')->assertSee('₦25,000.00')->assertSee('Whole property')->assertSee('New plan');
        $this->get('/config/memberships?edit=pl1')->assertSee('Edit plan');

        $this->post('/config/memberships', ['name' => 'Gold', 'durationDays' => 90, 'price' => '60000', 'facilityIds' => [F::FAC], 'active' => '1'])->assertSessionHas('success', 'Plan created.');
        Http::assertSent(fn (Request $r) => $r->method() === 'POST' && str_ends_with($r->url(), '/memberships/plans') && $r['price'] === '60000' && $r['durationDays'] === 90 && $r['facilityIds'] === [F::FAC] && $r['propertyWide'] === false);

        $this->patch('/config/memberships/pl1', ['name' => 'Monthly Gym', 'durationDays' => 30, 'price' => '27000.5', 'active' => '0'])->assertSessionHas('success', 'Plan updated.');
        Http::assertSent(fn (Request $r) => $r->method() === 'PATCH' && str_ends_with($r->url(), '/memberships/plans/pl1') && $r['active'] === false && $r['propertyWide'] === true);
    }

    public function test_membership_price_must_be_a_decimal_string(): void
    {
        $this->api();
        $this->signIn(self::ADMIN)->post('/config/memberships', ['name' => 'X', 'durationDays' => 30, 'price' => '1e5'])->assertSessionHasErrors('price');
    }

    public function test_booking_resources_show_strategies_and_pending_write_notice(): void
    {
        $this->api();
        $this->signIn(self::ADMIN)->get('/config/bookings')->assertOk()->assertSee('Tennis Court 1')->assertSee('Not reported by the API')->assertSee('A. Offline allocation')->assertSee('B. Online authority required')->assertSee('C. Pause online availability')->assertSee('data-testid="pending-api"', false)
            ->assertDontSee('Stale after (s)');
    }

    public function test_offline_allocation_strategy_form_lights_up_with_the_contract(): void
    {
        Contract::fake(['PATCH /bookings/{resourceId}'.'']);
        Contract::flush();
        Contract::fake(['PATCH /bookings/resources/{resourceId}']);
        $this->api(['PATCH /bookings/resources/*' => ['id' => 'res1']]);
        $this->signIn(self::ADMIN)->get('/config/bookings')->assertSee('B: online authority required')->assertSee('Stale after (s)');

        $this->patch('/config/bookings/res1', ['offlineAllocationStrategy' => 'A', 'offlineReserveCapacity' => '5', 'onlineStalenessThresholdSeconds' => '300'])->assertSessionHas('success');
        Http::assertSent(fn (Request $r) => $r->method() === 'PATCH' && $r['offlineAllocationStrategy'] === 'A' && $r['offlineReserveCapacity'] === '5');

        $this->patch('/config/bookings/res1', ['offlineAllocationStrategy' => 'Z'])->assertSessionHasErrors('offlineAllocationStrategy');
    }

    public function test_ticket_types_are_honestly_pending(): void
    {
        $this->api();
        $this->signIn(self::ADMIN)->get('/config/tickets')->assertOk()->assertSee('Ticket types')->assertSee('no /ticket-types endpoint in the contract');
    }

    public function test_kds_routing_maps_products_to_stations(): void
    {
        $this->api();
        $this->signIn(self::ADMIN)->get('/config/kds')->assertOk()->assertSee('Kitchen')->assertSee('Jollof Rice');
    }

    public function test_payment_timing_and_rules_per_facility(): void
    {
        $this->api();
        $this->signIn(self::ADMIN)->get('/config/payments')->assertOk()->assertSee('Main Restaurant')->assertSee('₦5,000.00')->assertSee('CASH_ONLY')->assertSee('not reported');
    }

    public function test_endpoint_missing_on_the_server_degrades_not_errors(): void
    {
        $this->api(['GET /admin/settings/tax' => [501, []], 'GET /memberships/plans' => [404, []]]);
        $this->signIn(self::ADMIN)->get('/config/tax')->assertOk()->assertSee('Tax setting is not available');
        $this->get('/config/memberships')->assertOk()->assertSee('Plans is not available');
    }

    public function test_settings_that_the_contract_lacks_are_not_even_called(): void
    {
        Contract::fake([]);
        config(['r007.disabled_endpoints' => ['GET /admin/settings/tax']]);
        $this->api();
        $this->signIn(self::ADMIN)->get('/config/tax')->assertOk()->assertSee('waiting on an API endpoint that is not in the contract yet');
        $this->assertFalse($this->sentTo('GET', '/admin/settings/tax'));
    }
}
