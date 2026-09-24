<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Http;
use Tests\Support\RealApi;
use Tests\TestCase;

/** Catalog, ticket types, membership plans and booking resources (real recorded shapes; what each save sends). */
class CatalogSetupTest extends TestCase
{
    private function api(array $over = []): void
    {
        $this->fakeApi($over + RealApi::routes(true));
        $this->signIn(RealApi::ownerPermissions(), ['OWNER']);
    }

    /** @return array<string, mixed> */
    private function product(): array
    {
        return RealApi::load('admin-catalog-product');
    }

    public function test_the_product_list_uses_the_admin_catalog_and_offers_filters_and_add(): void
    {
        $this->api();
        $h = $this->get('/setup/catalog?q=afang&category=x&active=1')->assertOk()->assertSee('Afang Soup')->assertSee('Add product')->assertSee('Standard VAT')->getContent();
        $this->assertStringContainsString('data-testid="products"', $h);
        $this->assertTrue($this->sentTo('GET', '/admin/catalog/products', fn ($r) => str_contains($r->url(), 'q=afang') && str_contains($r->url(), 'active=true')));
    }

    public function test_creating_a_product_posts_the_form_and_lands_on_its_editor(): void
    {
        $this->api(['POST /catalog/products' => [201, ['id' => $this->product()['id']]]]);
        $this->post('/setup/catalog/products', ['sku' => 'ZZ-1', 'name' => 'ZZ Test', 'categoryId' => $this->product()['categoryId'], 'kind' => 'GOOD', 'price' => '1500.00', 'trackStock' => '1', 'facilityIds' => [RealApi::FAC]])
            ->assertRedirect('/setup/catalog/products/'.$this->product()['id']);
        $b = $this->lastSent('POST', 'catalog/products')->data();
        $this->assertSame('1500.00', $b['price'], 'money stays a decimal string');
        $this->assertTrue($b['trackStock']);
        $this->assertSame([RealApi::FAC], $b['facilityIds']);
        $this->post('/setup/catalog/products', ['sku' => 'ZZ-1', 'name' => 'x', 'categoryId' => $this->product()['categoryId'], 'kind' => 'GOOD', 'price' => '12.5.5'])->assertSessionHasErrors('price');
    }

    public function test_product_editor_shows_where_it_is_sold_prices_and_stock(): void
    {
        $this->api();
        $this->get('/setup/catalog/products/'.$this->product()['id'])->assertOk()->assertSee('Where it is sold')->assertSee('Restaurant')->assertSee('Set a new price')->assertSee('Stock used per sale')->assertSee('Prepared by');
    }

    public function test_saving_details_sends_prep_route_tax_and_the_etag(): void
    {
        $this->api(['PATCH /catalog/products/*' => $this->product()]);
        $p = $this->product();
        $this->patch('/setup/catalog/products/'.$p['id'], ['name' => 'Afang', 'categoryId' => $p['categoryId'], 'kind' => 'GOOD', 'prepRouteId' => $p['prepRouteId'], 'taxRateId' => $p['taxRateId'], 'active' => '1', 'trackStock' => '0', 'etag' => '"1"'])->assertRedirect()->assertSessionHas('success');
        $req = $this->lastSent('PATCH', 'catalog/products/'.$p['id']);
        $this->assertSame($p['prepRouteId'], $req['prepRouteId']);
        $this->assertSame($p['taxRateId'], $req['taxRateId']);
        $this->assertFalse($req['trackStock']);
        $this->assertSame('"1"', $req->header('If-Match')[0]);
    }

    public function test_selling_at_a_facility_price_override_and_the_86_switch(): void
    {
        $p = $this->product();
        $this->api(['PUT /catalog/products/*/facilities/*' => ['ok' => true], 'DELETE /catalog/products/*/facilities/*' => [204, []], 'PUT /catalog/products/*/availability/*' => ['ok' => true]]);
        $this->put('/setup/catalog/products/'.$p['id'].'/facilities/'.RealApi::FAC, ['available' => '1', 'price' => '6000.00', 'unavailableReason' => ''])->assertRedirect();
        $this->assertSame(['available' => true, 'unavailableReason' => null, 'kdsStationId' => null, 'price' => '6000.00'], $this->lastSent('PUT', 'catalog/products/'.$p['id'].'/facilities/'.RealApi::FAC)->data());
        $this->delete('/setup/catalog/products/'.$p['id'].'/facilities/'.RealApi::FAC)->assertRedirect();
        $this->put('/setup/catalog/'.$p['id'].'/availability', ['facilityId' => RealApi::FAC, 'available' => '0', 'reason' => 'Sold out'])->assertSessionHas('success', fn ($m) => str_contains($m, '86'));
    }

    public function test_a_new_price_is_posted_as_a_price_row_not_an_edit(): void
    {
        $p = $this->product();
        $this->api(['POST /catalog/prices' => [201, ['id' => 'x']]]);
        $this->post('/setup/catalog/products/'.$p['id'].'/prices', ['amount' => '6200.00', 'scope' => 'ONE', 'facilityId' => RealApi::FAC, 'validFrom' => '2026-10-01'])->assertRedirect()->assertSessionHas('success');
        $this->assertSame(['productId' => $p['id'], 'amount' => '6200.00', 'facilityId' => RealApi::FAC, 'validFrom' => '2026-10-01T00:00:00Z'], $this->lastSent('POST', 'catalog/prices')->data());
        $this->post('/setup/catalog/products/'.$p['id'].'/prices', ['amount' => '6300', 'scope' => 'ALL'])->assertRedirect();
        $this->assertArrayNotHasKey('facilityId', $this->lastSent('POST', 'catalog/prices')->data());
    }

    public function test_a_price_conflict_is_explained_in_words(): void
    {
        $p = $this->product();
        $this->api(['POST /catalog/prices' => [409, ['type' => 'x', 'title' => 'Conflict', 'status' => 409, 'code' => 'price_overlap', 'detail' => 'A price already covers that period for this product at this facility.']]]);
        $this->from('/x')->post('/setup/catalog/products/'.$p['id'].'/prices', ['amount' => '1', 'scope' => 'ALL'])->assertSessionHas('error', 'A price already covers that period for this product at this facility.');
    }

    public function test_stock_links_replace_the_set_and_can_be_emptied(): void
    {
        $p = $this->product();
        $this->api(['PUT /catalog/products/*/stock-links' => ['links' => []]]);
        $this->put('/setup/catalog/products/'.$p['id'].'/stock-links', ['links' => [['stockItemId' => 'a1', 'quantityPerUnit' => '0.5'], ['stockItemId' => '', 'quantityPerUnit' => '2']]])->assertRedirect();
        $this->assertSame(['links' => [['stockItemId' => 'a1', 'quantityPerUnit' => '0.5']]], $this->lastSent('PUT', 'catalog/products/'.$p['id'].'/stock-links')->data());
        $this->put('/setup/catalog/products/'.$p['id'].'/stock-links', [])->assertSessionHas('success', fn ($m) => str_contains($m, 'no longer deducts'));
    }

    public function test_categories_tax_rates_and_price_lists(): void
    {
        $this->api(['POST /catalog/*' => [201, ['id' => 'x']], 'PATCH /catalog/tax-rates/*' => ['id' => 'x']]);
        foreach (['categories', 'prices', 'tax'] as $tab) {
            $this->get('/setup/catalog?tab='.$tab)->assertOk();
        }
        $this->get('/setup/catalog?tab=tax')->assertSee('Standard VAT')->assertSee('VAT_STD');
        $this->post('/setup/catalog/categories', ['name' => 'Snacks', 'sortOrder' => 40])->assertRedirect();
        $this->assertSame(['name' => 'Snacks', 'sortOrder' => 40], $this->lastSent('POST', 'catalog/categories')->data());
        $this->post('/setup/catalog/categories/'.$this->product()['categoryId'].'/route', ['prepRouteId' => $this->product()['prepRouteId'], 'applyToProducts' => '1'])->assertRedirect();
        $this->assertTrue($this->lastSent('POST', 'catalog/categories/'.$this->product()['categoryId'].'/prep-route')['applyToProducts']);
        $this->post('/setup/catalog/tax-rates', ['code' => 'levy', 'name' => 'Levy', 'ratePercent' => '5'])->assertRedirect();
        $this->assertSame('LEVY', $this->lastSent('POST', 'catalog/tax-rates')['code']);
        $this->post('/setup/catalog/price-lists', ['name' => 'Weekend'])->assertRedirect();
    }

    public function test_import_checks_first_then_applies_the_same_file_and_reports_row_errors(): void
    {
        $dry = ['dryRun' => true, 'totalRows' => 3, 'valid' => 2, 'willCreate' => 1, 'willUpdate' => 1, 'unchanged' => 0, 'createdCategories' => ['Bakery'], 'errors' => [], 'applied' => false];
        $this->api(['POST /catalog/products/import' => fn ($r) => str_contains($r->url(), 'dryRun=false') ? array_replace($dry, ['dryRun' => false, 'applied' => true]) : $dry]);
        $csv = "sku,name,category,price\nZZ-1,ZZ,Bakery,1500\n";

        $r = $this->followingRedirects()->post('/setup/catalog/import/products', ['csv' => $csv]);
        $r->assertSee('Check passed: nothing has been changed yet')->assertSee('Will create')->assertSee('Bakery')->assertSee('Apply this import');
        $sent = $this->lastSent('POST', 'catalog/products/import');
        $this->assertStringContainsString('dryRun=true', $sent->url());
        $this->assertSame(trim($csv), trim($sent->body()));
        $this->assertSame('text/csv', $sent->header('Content-Type')[0]);

        $r = $this->followingRedirects()->post('/setup/catalog/import/products', ['mode' => 'apply']);
        $r->assertSee('Import applied');
        $this->assertStringContainsString('dryRun=false', $this->lastSent('POST', 'catalog/products/import')->url());
        $this->assertSame(trim($csv), trim($this->lastSent('POST', 'catalog/products/import')->body()), 'the applied file is the one that was checked');
    }

    public function test_an_import_with_errors_lists_them_and_offers_no_apply_button(): void
    {
        $this->api(['POST /catalog/products/import' => [422, ['type' => 'x', 'title' => 'Unprocessable', 'status' => 422, 'code' => 'import_validation_failed', 'detail' => 'The file has errors.', 'dryRun' => true, 'totalRows' => 2, 'valid' => 1, 'willCreate' => 1, 'willUpdate' => 0, 'unchanged' => 0, 'createdCategories' => [], 'errors' => [['row' => 3, 'field' => 'price', 'message' => 'Price must be a number.']], 'applied' => false]]]);
        $this->followingRedirects()->post('/setup/catalog/import/products', ['csv' => "sku,name\nA,B\n"])
            ->assertSee('Fix these rows')->assertSee('Price must be a number.')->assertDontSee('Apply this import');
    }

    public function test_export_streams_the_apis_csv(): void
    {
        $this->api(['GET /catalog/products/export' => fn () => [200, 'sku,name'."\n".'A,B', ['Content-Type' => 'text/csv']]]);
        $r = $this->get('/setup/catalog/export/products')->assertOk();
        $this->assertStringContainsString('text/csv', $r->headers->get('Content-Type'));
        $this->assertStringContainsString('filename="products-', $r->headers->get('Content-Disposition'));
        $this->get('/setup/catalog/export/nothing')->assertNotFound();
    }

    public function test_ticket_types_list_create_and_edit(): void
    {
        $tt = RealApi::load('ticket-types')['items'][0];
        $this->api(['POST /ticketing/ticket-types' => [201, $tt], 'PATCH /ticketing/ticket-types/*' => $tt]);
        $this->get('/setup/tickets')->assertOk()->assertSee('Pool - Adult')->assertSee('One entry')->assertSee('Add ticket type');
        $this->get('/setup/tickets?tab=issued')->assertOk()->assertSee('Issued tickets');
        $this->post('/setup/tickets', ['code' => 'gym-day', 'name' => 'Gym day', 'facilityId' => RealApi::FAC, 'format' => 'INDIVIDUAL', 'validationMode' => 'ENTRY_EXIT', 'validityKind' => 'DURATION_MINUTES', 'validityMinutes' => 180, 'earlyEntryMinutes' => 10, 'price' => '2000', 'active' => '1'])->assertRedirect('/setup/tickets');
        $b = $this->lastSent('POST', 'ticketing/ticket-types')->data();
        $this->assertSame('GYM-DAY', $b['code']);
        $this->assertSame(180, $b['validityMinutes']);
        $this->assertSame('2000', $b['price']);
        $this->patch('/setup/tickets/'.$tt['id'], ['name' => 'Pool adult', 'format' => 'INDIVIDUAL', 'validationMode' => 'SINGLE_USE', 'validityKind' => 'ISSUE_DAY', 'validityMinutes' => 99, 'etag' => '"1"'])->assertRedirect();
        $req = $this->lastSent('PATCH', 'ticketing/ticket-types/'.$tt['id']);
        $this->assertNull($req['validityMinutes'], 'minutes only apply to "valid for a set time"');
        $this->assertSame('"1"', $req->header('If-Match')[0]);
        $this->post('/setup/tickets', ['name' => 'x', 'facilityId' => RealApi::FAC, 'format' => 'INDIVIDUAL', 'validationMode' => 'NOPE', 'validityKind' => 'ISSUE_DAY'])->assertSessionHasErrors(['code', 'validationMode']);
    }

    public function test_membership_plan_scope_and_perks(): void
    {
        $this->api(['POST /memberships/plans' => [201, ['id' => 'p']], 'PATCH /memberships/plans/*' => ['id' => 'p']]);
        $this->get('/setup/memberships')->assertOk()->assertSee('Gold Monthly')->assertSee('Whole property')->assertSee('Add plan');
        $this->post('/setup/memberships', ['code' => 'silver', 'name' => 'Silver', 'durationDays' => 30, 'price' => '50000.00', 'memberDiscountPercent' => '10', 'visitLimit' => '', 'bookingAdvanceDays' => 3, '_scope' => 'SOME', 'facilityIds' => [RealApi::FAC], 'active' => '1'])->assertRedirect();
        $b = $this->lastSent('POST', 'memberships/plans')->data();
        $this->assertSame('SILVER', $b['code']);
        $this->assertFalse($b['propertyWide']);
        $this->assertSame([RealApi::FAC], $b['facilityIds']);
        $this->assertNull($b['visitLimit'], 'blank = unlimited');
        $this->post('/setup/memberships', ['code' => 'all', 'name' => 'All', 'durationDays' => 30, 'price' => '1', '_scope' => 'ALL', 'facilityIds' => [RealApi::FAC]])->assertRedirect();
        $b = $this->lastSent('POST', 'memberships/plans')->data();
        $this->assertTrue($b['propertyWide']);
        $this->assertSame([], $b['facilityIds']);
    }

    public function test_booking_resources_list_and_editor(): void
    {
        $r = RealApi::load('booking-resources')['items'][0];
        $this->api();
        $this->get('/setup/bookings')->assertOk()->assertSee($r['name'])->assertSee('Reserved share');
        $h = $this->get('/setup/bookings/'.$r['id'])->assertOk()->assertSee('When the site is offline')->assertSee('A. Reserved share')->assertSee('B. Needs the internet')->assertSee('C. Website pauses')->assertSee('Opening windows')->assertSee('Blackout dates')->assertSee('Rules for this resource')->getContent();
        $this->assertStringContainsString('name="reservePercent"', $h);
        $this->assertStringContainsString('name="onlineStaleAfterSeconds"', $h);
    }

    public function test_the_reserve_slider_is_a_percent_that_becomes_whole_units(): void
    {
        $r = RealApi::load('booking-resources')['items'][0];
        $this->api(['PATCH /bookings/resources/*' => $r]);
        $this->patch('/setup/bookings/'.$r['id'], ['name' => 'Court', 'offlineStrategy' => 'A_OFFLINE_ALLOCATION', 'reservePercent' => '30', 'capacity' => 10, 'slotMinutes' => 60, 'onlineStaleAfterSeconds' => 1200, 'active' => '1', 'onlineBookable' => '1'])->assertRedirect()->assertSessionHas('success');
        $b = $this->lastSent('PATCH', 'bookings/resources/'.$r['id'])->data();
        $this->assertSame(['offlineStrategy' => 'A_OFFLINE_ALLOCATION', 'localReserveUnits' => 3, 'onlineStaleAfterSeconds' => 1200], $b['authority']);
        $this->assertTrue($b['onlineBookable']);
        $this->patch('/setup/bookings/'.$r['id'], ['name' => 'Court', 'offlineStrategy' => 'C_DISABLE_ONLINE', 'reservePercent' => '30', 'capacity' => 10])->assertRedirect();
        $this->assertArrayNotHasKey('localReserveUnits', $this->lastSent('PATCH', 'bookings/resources/'.$r['id'])['authority'], 'the reserve only applies to strategy A');
    }

    public function test_weekly_windows_become_day_of_week_rows(): void
    {
        $r = RealApi::load('booking-resources')['items'][0];
        $this->api(['PUT /bookings/resources/*/schedule' => ['windows' => []]]);
        $days = ['mon' => [['08:00', '12:00'], ['14:00', '20:00']], 'sun' => [['10:00', '18:00']]];
        $week = ['weekly' => [], 'exceptions' => []];
        foreach (['mon', 'tue', 'wed', 'thu', 'fri', 'sat', 'sun'] as $d) {
            $week['weekly'][$d] = ['open' => isset($days[$d]), 'intervals' => array_map(fn ($i) => ['from' => $i[0], 'to' => $i[1]], $days[$d] ?? [])];
        }
        $this->put('/setup/bookings/'.$r['id'].'/schedule', ['hours' => json_encode($week)])->assertRedirect();
        $this->assertSame([['dayOfWeek' => 1, 'open' => '08:00', 'close' => '12:00'], ['dayOfWeek' => 1, 'open' => '14:00', 'close' => '20:00'], ['dayOfWeek' => 7, 'open' => '10:00', 'close' => '18:00']], $this->lastSent('PUT', 'bookings/resources/'.$r['id'].'/schedule')['windows']);
    }

    public function test_rule_overrides_only_send_what_differs_from_the_facility_and_can_be_cleared(): void
    {
        $r = RealApi::load('booking-resources')['items'][0];
        $this->api(['PUT /bookings/resources/*/rules' => RealApi::load('booking-resource-rules')]);
        $eff = RealApi::load('booking-resource-rules')['effective'];
        $this->put('/setup/bookings/'.$r['id'].'/rules', ['rules' => ['hold_ttl_seconds' => 900, 'min_notice_minutes' => $eff['minNoticeMinutes'], 'cancel_fee_percent' => 20]])->assertRedirect()->assertSessionHas('success');
        $b = $this->lastSent('PUT', 'bookings/resources/'.$r['id'].'/rules')->data();
        $this->assertSame(900, $b['holdTtlSeconds']);
        $this->assertSame(20, $b['cancelFeePercent']);
        $this->assertNull($b['minNoticeMinutes'], 'the same as the facility = keep inheriting');
        $this->put('/setup/bookings/'.$r['id'].'/rules', ['clear' => '1'])->assertSessionHas('success', fn ($m) => str_contains($m, 'follows the facility'));
        $this->assertNull($this->lastSent('PUT', 'bookings/resources/'.$r['id'].'/rules')['holdTtlSeconds']);
    }

    public function test_a_blackout_covers_whole_property_days_in_utc(): void
    {
        $r = RealApi::load('booking-resources')['items'][0];
        $this->api(['POST /bookings/resources/*/blackouts' => [201, ['id' => 'b']], 'DELETE /bookings/blackouts/*' => [204, []]]);
        $this->post('/setup/bookings/'.$r['id'].'/blackouts', ['from' => '2026-12-24', 'to' => '2026-12-25', 'reason' => 'Christmas'])->assertRedirect();
        $b = $this->lastSent('POST', 'bookings/resources/'.$r['id'].'/blackouts')->data();
        $this->assertSame('2026-12-23T23:00:00+00:00', $b['start'], 'midnight in Lagos (UTC+1)');
        $this->assertSame('2026-12-25T23:00:00+00:00', $b['end'], 'end of the last day');
        $this->post('/setup/bookings/'.$r['id'].'/blackouts', ['from' => '2026-12-25', 'to' => '2026-12-24'])->assertSessionHasErrors('to');
        $this->delete('/setup/bookings/'.$r['id'].'/blackouts/3b2c56b0-3b8e-4b2e-9d8c-000000000001')->assertRedirect();
        $this->assertNotNull(Http::recorded()->first(fn ($p) => $p[0]->method() === 'DELETE'));
    }
}
