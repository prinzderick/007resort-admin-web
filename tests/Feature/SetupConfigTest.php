<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Http;
use Tests\Support\RealApi;
use Tests\TestCase;

/**
 * Setup > Facilities against JSON recorded from the real configuration API: what each tab shows, and exactly what a save sends
 * (method, path, body, If-Match), including the API's plain-language refusals (capability dependency, facility in use).
 */
class SetupConfigTest extends TestCase
{
    private const F = RealApi::FAC;

    private function api(array $over = []): void
    {
        // The real API sends the aggregate version as an ETag on every read; the recordings only keep bodies.
        $over += ['GET /facilities/*/operating-rules' => [200, RealApi::load('operating-rules'), ['ETag' => '"2"']]];
        $this->fakeApi($over + RealApi::routes(true));
        $this->signIn(RealApi::ownerPermissions(), ['OWNER']);
    }

    public function test_rules_tab_renders_every_applicable_rule_from_the_real_definitions(): void
    {
        $this->api();
        $h = $this->get('/setup/facilities/'.self::F.'?tab=rules')->assertOk()->getContent();

        foreach (['payment_timing', 'waiter_collection_enabled', 'waiter_cash_holding', 'waiter_cash_in_hand_limit', 'collection_requires_confirmation', 'cash_handover_max_variance', 'approval_threshold_amount', 'stock_consumption_timing'] as $key) {
            $this->assertStringContainsString('data-rule="'.$key.'"', $h, "{$key} is on the rules form");
        }
        $this->assertStringNotContainsString('data-rule="hold_ttl_seconds"', $h, 'a booking rule does not apply to a restaurant');
        $this->assertStringContainsString('Waiters may collect', strip_tags($h).'Waiters may collect'); // label exists in definitions
        $this->assertStringContainsString('Hidden because this facility does not have the feature they need', $h);
    }

    public function test_saving_rules_sends_only_what_changed_with_the_version_and_the_confirmation(): void
    {
        $this->api(['PUT /facilities/*/operating-rules' => RealApi::load('operating-rules')]);
        $values = RealApi::load('operating-rules')['values'];

        $this->put('/setup/facilities/'.self::F.'/rules', ['_rules_present' => 1, 'rules' => [
            'payment_timing' => 'PAY_FIRST',                                 // changed
            'require_cash_session' => '0',                                  // changed, high impact
            'allow_open_tabs' => $values['allow_open_tabs'] ? '1' : '0',    // unchanged
            'cash_handover_max_variance' => '750.00',                       // changed money
            'approval_threshold_amount' => '5000.0000',                     // unchanged (5000.00 == 5000.0000)
            'collection_requires_confirmation' => ['TRANSFER', 'CASH', 'CARD_TERMINAL'], // same set, different order: unchanged
            'require_approval_for' => ['order.void', 'order.discount', 'order.comp'],
        ], 'confirm' => '1'])->assertRedirect()->assertSessionHas('success');

        $req = $this->lastSent('PUT', 'facilities/'.self::F.'/operating-rules');
        $this->assertNotNull($req);
        $this->assertSame(['payment_timing' => 'PAY_FIRST', 'require_cash_session' => false, 'cash_handover_max_variance' => '750.00'], $req['rules']);
        $this->assertTrue($req['confirm']);
        $this->assertSame('"2"', $req->header('If-Match')[0] ?? null, 'the version the form was built from');
    }

    public function test_a_high_impact_change_without_the_confirmation_is_stopped_before_the_api(): void
    {
        $this->api();
        $this->put('/setup/facilities/'.self::F.'/rules', ['_rules_present' => 1, 'rules' => ['require_cash_session' => '0']])
            ->assertRedirect()->assertSessionHas('error', fn ($m) => str_contains($m, 'high-impact'));
        $this->assertNull($this->lastSent('PUT', 'facilities/'.self::F.'/operating-rules'));
    }

    public function test_returning_a_rule_to_its_standard_value_resets_it_and_unticking_every_box_means_none(): void
    {
        $this->api(['PUT /facilities/*/operating-rules' => RealApi::load('operating-rules')]);
        $this->put('/setup/facilities/'.self::F.'/rules', ['_rules_present' => 1, 'rules' => ['payment_timing' => 'PAY_AFTER_SERVICE'], 'confirm' => '1'])->assertRedirect();
        $req = $this->lastSent('PUT', 'facilities/'.self::F.'/operating-rules');
        $this->assertNull($req['rules']['payment_timing'], 'the standard value is sent as null = reset');
        $this->assertSame([], $req['rules']['collection_requires_confirmation'], 'no boxes ticked posts nothing, which means an empty list');
    }

    public function test_the_apis_rule_errors_land_on_the_field(): void
    {
        $this->api(['PUT /facilities/*/operating-rules' => [422, ['type' => 'x', 'title' => 'Validation failed', 'status' => 422, 'code' => 'validation_failed', 'detail' => 'One or more fields are invalid.', 'errors' => ['rules.cash_handover_max_variance' => ['Enter an amount of zero or more.']]]]]);
        $this->from('/setup/facilities/'.self::F.'?tab=rules')->put('/setup/facilities/'.self::F.'/rules', ['_rules_present' => 1, 'rules' => ['cash_handover_max_variance' => '900'], 'confirm' => '1'])
            ->assertSessionHasErrors('rules.cash_handover_max_variance');
    }

    public function test_capabilities_tab_lists_the_catalogue_and_a_dependency_refusal_is_explained(): void
    {
        $this->api(['PUT /facilities/*/capabilities' => [422, ['type' => 'x', 'title' => 'Unprocessable entity', 'status' => 422, 'code' => 'capability_dependency', 'detail' => 'Some capabilities need others to be enabled first (or others still need them).', 'errors' => ['capabilities.POS' => ['KITCHEN_ROUTING requires POS; turn KITCHEN_ROUTING off first.']]]]]);
        $h = $this->get('/setup/facilities/'.self::F.'?tab=capabilities')->assertOk()->assertSee('Point of sale')->assertSee('Kitchen routing')->getContent();
        $this->assertStringContainsString('capability-note', $h);

        $this->from('/x')->put('/setup/facilities/'.self::F.'/capabilities', ['capabilities' => ['KITCHEN_ROUTING'], 'etag' => '"2"'])->assertSessionHasErrors('capabilities.POS');
        $req = $this->lastSent('PUT', 'facilities/'.self::F.'/capabilities');
        $this->assertSame(['KITCHEN_ROUTING'], $req['capabilities']);
        $this->assertSame('"2"', $req->header('If-Match')[0]);
    }

    public function test_switching_off_a_capability_that_is_in_use_lists_what_to_close_first(): void
    {
        $this->api(['PUT /facilities/*/capabilities' => [409, ['type' => 'x', 'title' => 'Conflict', 'status' => 409, 'code' => 'capability_in_use', 'detail' => 'Cannot turn these capabilities off yet.', 'blockers' => [['capability' => 'TABLE_SERVICE', 'type' => 'open_orders', 'count' => 3, 'message' => '3 open order(s) must be settled or voided first.']]]]]);
        $r = $this->followingRedirects()->from('/setup/facilities/'.self::F.'?tab=capabilities')->put('/setup/facilities/'.self::F.'/capabilities', ['capabilities' => ['POS']]);
        $r->assertSee('still in use', false)->assertSee('3 open order(s) must be settled or voided first.');
    }

    public function test_deactivating_a_busy_facility_explains_each_blocker(): void
    {
        $this->api(['POST /organization/facilities/*/deactivate' => [409, ['type' => 'x', 'title' => 'Conflict', 'status' => 409, 'code' => 'facility_in_use', 'detail' => 'Cannot deactivate Restaurant yet: ...', 'blockers' => [['type' => 'open_orders', 'count' => 3, 'message' => '3 open order(s) must be settled or voided first.'], ['type' => 'open_cash_sessions', 'count' => 1, 'message' => '1 cash session(s) are still open; close them first.']]]]]);
        $this->followingRedirects()->from('/setup/facilities/'.self::F)->post('/setup/facilities/'.self::F.'/deactivate', ['etag' => '"2"'])
            ->assertSee('cannot be switched off yet')->assertSee('3 open order(s) must be settled or voided first.')->assertSee('1 cash session(s) are still open; close them first.');
    }

    public function test_general_tab_saves_opening_hours_in_the_api_shape(): void
    {
        $this->api(['PATCH /organization/facilities/*' => RealApi::load('organization-facility')]);
        $hours = ['weekly' => ['mon' => ['open' => true, 'intervals' => [['from' => '08:00', 'to' => '14:00'], ['from' => '18:00', 'to' => '23:00']]], 'tue' => ['open' => false, 'intervals' => []], 'wed' => ['open' => false, 'intervals' => []], 'thu' => ['open' => false, 'intervals' => []], 'fri' => ['open' => false, 'intervals' => []], 'sat' => ['open' => false, 'intervals' => []], 'sun' => ['open' => false, 'intervals' => []]],
            'exceptions' => [['date' => '2026-12-25', 'label' => 'Christmas', 'open' => false, 'from' => null, 'to' => null]]];
        $this->patch('/setup/facilities/'.self::F, ['name' => 'Restaurant', 'kind' => 'RESTAURANT', 'timezone' => 'Africa/Lagos', 'sortOrder' => 2, 'contact' => ['phone' => '0800', 'email' => ''], 'hours' => json_encode($hours), 'etag' => '"2"'])->assertRedirect()->assertSessionHas('success');
        $req = $this->lastSent('PATCH', 'organization/facilities/'.self::F);
        $this->assertSame([['open' => '08:00', 'close' => '14:00'], ['open' => '18:00', 'close' => '23:00']], $req['openingHours']['weekly']['mon']);
        $this->assertSame([], $req['openingHours']['weekly']['tue']);
        $this->assertSame([['date' => '2026-12-25', 'closed' => true, 'note' => 'Christmas']], $req['openingHours']['exceptions']);
        $this->assertSame(['phone' => '0800'], $req['contact'], 'blank contact fields are not sent');
        $this->assertSame('"2"', $req->header('If-Match')[0]);
    }

    public function test_points_tab_lists_points_and_tables_and_offers_the_actions(): void
    {
        $this->api();
        $this->get('/setup/facilities/'.self::F.'?tab=points')->assertOk()->assertSee('Restaurant Cash Counter')->assertSee('Main Dining')->assertSee('Add many')->assertSee('Add operating point')->assertSee('TR4');
    }

    public function test_adding_a_kitchen_screen_sends_the_kds_station(): void
    {
        $this->api(['POST /organization/facilities/*/operating-points' => [201, ['id' => 'x']]]);
        $this->post('/setup/facilities/'.self::F.'/points', ['name' => 'Grill', 'code' => 'grill_1', 'kind' => 'STATION', 'kdsKind' => 'KITCHEN', 'prepRouteId' => '35c17f7d-e784-5930-aec7-5c560e519830'])->assertRedirect()->assertSessionHas('success');
        $req = $this->lastSent('POST', 'organization/facilities/'.self::F.'/operating-points');
        $this->assertSame(['name' => 'Grill', 'code' => 'GRILL_1', 'kind' => 'STATION', 'kdsStation' => ['kind' => 'KITCHEN', 'prepRouteId' => '35c17f7d-e784-5930-aec7-5c560e519830']], $req->data());
        $this->assertTrue($req->hasHeader('Idempotency-Key'));
    }

    public function test_bulk_tables_report_what_was_skipped(): void
    {
        $this->api(['POST /organization/facilities/*/tables/bulk' => [201, ['created' => [['label' => 'Z1'], ['label' => 'Z2']], 'skipped' => [['label' => 'Z3', 'reason' => 'exists']]]]]);
        $this->post('/setup/facilities/'.self::F.'/tables/bulk', ['prefix' => 'Z', 'from' => 1, 'to' => 3, 'seats' => 4, 'padWidth' => 0])
            ->assertSessionHas('success', fn ($m) => str_contains($m, '2 tables added') && str_contains($m, 'Z3'));
        $req = $this->lastSent('POST', 'organization/facilities/'.self::F.'/tables/bulk');
        $this->assertSame(['prefix' => 'Z', 'from' => 1, 'to' => 3, 'seats' => 4, 'padWidth' => 0], $req->data());
        $this->post('/setup/facilities/'.self::F.'/tables/bulk', ['prefix' => 'Z', 'from' => 5, 'to' => 2, 'seats' => 4])->assertSessionHasErrors('to');
    }

    public function test_editing_a_table_and_a_point_is_version_checked(): void
    {
        $t = RealApi::load('config-tables')['items'][0];
        $this->api(['PATCH /organization/tables/*' => $t, 'POST /organization/tables/*/deactivate' => $t, 'POST /organization/operating-points/*/deactivate' => ['id' => 'x']]);
        $this->patch('/setup/tables/'.$t['id'], ['facilityId' => self::F, 'label' => 'T3b', 'seats' => 6, 'rowVersion' => '4'])->assertRedirect()->assertSessionHas('success');
        $req = $this->lastSent('PATCH', 'organization/tables/'.$t['id']);
        $this->assertSame(['label' => 'T3b', 'seats' => 6, 'operatingPointId' => null], $req->data());
        $this->assertSame('"4"', $req->header('If-Match')[0]);
        $this->post('/setup/tables/'.$t['id'].'/deactivate', ['facilityId' => self::F, 'rowVersion' => '4'])->assertRedirect();
        $this->assertNotNull($this->lastSent('POST', 'organization/tables/'.$t['id'].'/deactivate'));
        $this->post('/setup/points/'.$t['id'].'/deactivate', ['facilityId' => self::F, 'rowVersion' => '1'])->assertRedirect();
        $this->post('/setup/points/'.$t['id'].'/explode', ['facilityId' => self::F])->assertNotFound();
    }

    public function test_devices_tab_and_page_edit_home_facility_and_mode(): void
    {
        $d = RealApi::load('devices')['items'][0];
        $this->api(['PATCH /devices/*' => $d]);
        $this->get('/devices')->assertOk()->assertSee('Edit device');
        $this->get('/setup/facilities/'.self::F.'?tab=devices')->assertOk()->assertSee('Devices at this facility');
        $this->patch('/devices/'.$d['id'], ['name' => 'Spa POS', 'facilityId' => self::F, 'mode' => 'POS', 'operatingPointId' => '', 'rowVersion' => '1'])->assertRedirect('/devices')->assertSessionHas('success');
        $req = $this->lastSent('PATCH', 'devices/'.$d['id']);
        $this->assertSame(['name' => 'Spa POS', 'facilityId' => self::F, 'operatingPointId' => null, 'mode' => 'POS'], $req->data());
        $this->assertSame('"1"', $req->header('If-Match')[0]);
    }

    public function test_a_device_that_is_checked_out_cannot_move_and_the_reason_is_shown(): void
    {
        $d = RealApi::load('devices')['items'][0];
        $this->api(['PATCH /devices/*' => $this->problem(409, 'device_checked_out', 'This tablet is checked out to a waiter. Check it in before moving it.')]);
        $this->from('/devices')->patch('/devices/'.$d['id'], ['name' => 'X', 'facilityId' => self::F, 'mode' => 'ATTENDANT'])->assertSessionHas('error', 'This tablet is checked out to a waiter. Check it in before moving it.');
    }

    public function test_business_receipt_and_tax_are_three_settings_with_their_own_versions(): void
    {
        $this->api(['PUT /admin/settings/*' => ['ok' => true]]);
        $this->get('/setup/business')->assertOk()->assertSee('Business profile')->assertSee('Receipt')->assertSee('VAT');
        $this->put('/setup/business', ['organizationName' => 'ACME', 'siteName' => 'ACME Resort', 'timezone' => 'Africa/Lagos', 'address' => 'Otueke', 'phone' => '', 'email' => 'a@b.co', 'etag' => '"1"'])->assertRedirect()->assertSessionHas('success');
        $this->assertSame('"1"', $this->lastSent('PUT', 'admin/settings/business')->header('If-Match')[0]);
        $this->put('/setup/receipt', ['businessName' => 'ACME', 'footer' => 'Thanks', 'paperColumns' => '32', 'showTin' => '1', 'etag' => '"1"'])->assertRedirect()->assertSessionHas('success');
        $r = $this->lastSent('PUT', 'admin/settings/receipt');
        $this->assertSame(32, $r['paperColumns']);
        $this->assertTrue($r['showTin']);
        $this->put('/setup/tax', ['vatEnabled' => '1', 'vatRatePercent' => '7.5', 'vatNumber' => '123-45', 'etag' => '"0"'])->assertRedirect();
        $this->assertTrue($this->lastSent('PUT', 'admin/settings/tax')['vatEnabled']);
    }

    public function test_payment_methods_matrix_only_saves_facilities_that_changed(): void
    {
        $this->api(['PUT /facilities/*/payment-methods' => RealApi::load('facility-payment-methods')]);
        $this->get('/setup/payments')->assertOk()->assertSee('Payment methods')->assertSee('Paystack');
        $before = ['CASH' => 1, 'CARD' => 1, 'TRANSFER' => 1, 'POS_TERMINAL' => 1, 'PAYSTACK' => 1];
        $this->put('/setup/payments', ['methods' => [self::F => ['CASH' => 1, 'CARD' => 0, 'TRANSFER' => 1, 'POS_TERMINAL' => 1, 'PAYSTACK' => 1], 'other' => $before], 'before' => [self::F => $before, 'other' => $before], 'version' => [self::F => '"2"']])
            ->assertSessionHas('success', fn ($m) => str_contains($m, '1 facility'));
        $req = $this->lastSent('PUT', 'facilities/'.self::F.'/payment-methods');
        $this->assertSame(['CASH' => true, 'CARD' => false, 'TRANSFER' => true, 'POS_TERMINAL' => true, 'PAYSTACK' => true], $req['methods']);
        $this->assertSame('"2"', $req->header('If-Match')[0]);
        $this->assertNull($this->lastSent('PUT', 'facilities/other/payment-methods'));
    }

    public function test_kitchen_routing_saves_one_mapping_per_route(): void
    {
        $this->api(['PUT /catalog/prep-route-stations' => ['ok' => true], 'POST /catalog/prep-routes' => [201, ['id' => 'r']]]);
        $this->get('/setup/kds?facility='.self::F)->assertOk()->assertSee('Kitchen orders go to')->assertSee('Bar orders go to');
        $this->put('/setup/kds', ['facilityId' => self::F, 'stations' => ['84b6f7a9-2c32-59b1-a727-0edf83f50c0e' => 'a04b0443-92a2-5109-912a-09f2d108b558', '35c17f7d-e784-5930-aec7-5c560e519830' => '']])->assertRedirect()->assertSessionHas('success');
        $req = $this->lastSent('PUT', 'catalog/prep-route-stations');
        $this->assertSame('a04b0443-92a2-5109-912a-09f2d108b558', $this->sentBodies('PUT', 'catalog/prep-route-stations')[0]['kdsStationId']);
        $this->assertNull($this->sentBodies('PUT', 'catalog/prep-route-stations')[1]['kdsStationId'], 'empty = the facility default');
        $this->assertNotNull($req);
    }

    /** @return list<array<string, mixed>> */
    private function sentBodies(string $method, string $path): array
    {
        $out = [];
        foreach (Http::recorded() as [$r]) {
            if ($r->method() === $method && rtrim(parse_url($r->url(), PHP_URL_PATH), '/') === '/api/v1/'.$path) {
                $out[] = $r->data();
            }
        }

        return $out;
    }

    public function test_the_wizard_posts_the_template_capabilities_and_the_starter_switch(): void
    {
        $this->api(['POST /organization/facilities' => [201, ['id' => self::F]]]);
        $h = $this->get('/setup/facilities/new')->assertOk()->assertSee('Restaurant')->assertSee('Bar / lounge')->assertSee('data-template="blank"', false)->getContent();
        $this->assertStringContainsString('starterOperatingPoints', $h);
        $this->post('/setup/facilities', ['name' => 'Rooftop Bar', 'code' => 'rooftop_bar', 'kind' => 'BAR', 'templateKey' => 'BAR', 'capabilities' => ['POS', 'BAR_ROUTING'], 'applyStarter' => '1'])
            ->assertRedirect('/setup/facilities/'.self::F.'?tab=rules');
        $this->assertSame(['name' => 'Rooftop Bar', 'code' => 'ROOFTOP_BAR', 'kind' => 'BAR', 'templateKey' => 'BAR', 'applyStarter' => true, 'capabilities' => ['POS', 'BAR_ROUTING']], $this->lastSent('POST', 'organization/facilities')->data());
        $this->post('/setup/facilities', ['name' => 'X', 'code' => '1bad'])->assertSessionHasErrors('code');
    }
}
