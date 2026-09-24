<?php

namespace Tests\Feature;

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\Support\Fixtures as F;
use Tests\TestCase;

class ReportsTest extends TestCase
{
    private function api(array $o = []): void
    {
        $this->fakeApi($o + [
            'GET /organization/facilities' => F::facilities(),
            'GET /reports/facility-daily-summary' => fn (Request $r) => F::summary(str_contains($r->url(), F::FAC2) ? F::FAC2 : F::FAC, str_contains($r->url(), F::FAC2) ? '50000.0000' : '100000.0000'),
            'GET /reports/revenue' => ['from' => '2026-09-17', 'to' => '2026-09-23', 'currency' => 'NGN', 'orders' => 40, 'revenue' => '900000.0000', 'byFacility' => [['facilityId' => F::FAC, 'code' => 'R', 'name' => 'Main Restaurant', 'orders' => 30, 'revenue' => '600000.0000']], 'byOperatingPoint' => [], 'byPaymentMethod' => [['tenderType' => 'CASH', 'count' => 4, 'captured' => '700000.0000', 'refunded' => '0.0000', 'net' => '700000.0000']], 'freshness' => F::freshness()],
            'GET /organization/facilities/*/operating-points' => F::page([['id' => 'op1', 'facilityId' => F::FAC, 'code' => 'FLOOR', 'name' => 'Main floor', 'kind' => 'TABLE_AREA']]),
            'GET /organization/facilities/*' => F::facilities()['items'][0],
            'GET /cash-sessions' => F::page([['id' => F::SESSION, 'facilityId' => F::FAC, 'deviceId' => 'dev-1', 'staffId' => F::STAFF, 'status' => 'CLOSED', 'openingFloat' => '20000.0000', 'expectedCash' => '95000.0000', 'countedCash' => '94500.0000', 'variance' => '-500.0000', 'openedAt' => '2026-09-23T07:00:00.000000Z', 'closedAt' => null]]),
            'GET /devices' => F::page([['id' => 'dev-1', 'name' => 'POS-01']]),
            'GET /reports/cashier-shift/*' => ['shiftId' => F::SESSION, 'staffId' => F::STAFF, 'staffName' => 'Bisi Lawal', 'facilityId' => F::FAC, 'openedAt' => '2026-09-23T07:00:00.000000Z', 'closedAt' => null, 'openingFloat' => '20000.0000',
                'byTender' => [['tenderType' => 'CASH', 'amount' => '75000.0000', 'count' => 9]], 'expectedCash' => '95000.0000', 'countedCash' => '94500.0000', 'variance' => '-500.0000', 'refunds' => '0.0000', 'voids' => 0, 'paymentIds' => [], 'freshness' => F::freshness()],
            'GET /payments' => F::page([F::payment(F::PAY), F::payment('p2', 'CAPTURED', '=1+1', ['providerReference' => '=HYPERLINK("http://evil")'])]),
        ]);
    }

    public function test_property_level_lists_every_facility_with_totals(): void
    {
        $this->api();
        $this->signIn(['report.view'])->get('/reports?date=2026-09-23')->assertOk()
            ->assertSee('Main Restaurant')->assertSee('Pool Bar')->assertSee('₦150,000.00')->assertSee('₦900,000.00')->assertSee('Period revenue');
    }

    public function test_facility_level_drills_to_terminals_and_shift_reports(): void
    {
        $this->api();
        $this->signIn(['report.view'])->get('/reports/facility/'.F::FAC.'?date=2026-09-23')->assertOk()
            ->assertSee('Main Restaurant report')->assertSee('Payments by method')->assertSee('Jollof Rice')
            ->assertSee('Main floor')->assertSee('POS-01')->assertSee('Shift report')
            ->assertSee(route('reports.shift', F::SESSION), false)
            ->assertSee('Revenue per operating point is not available yet');
    }

    public function test_shift_level_shows_cashier_reconciliation_and_transactions(): void
    {
        $this->api();
        $this->signIn(['report.view'])->get('/reports/shift/'.F::SESSION)->assertOk()
            ->assertSee('Bisi Lawal')->assertSee('-₦500.00')->assertSee('Takings by method')->assertSee(route('finance.payment', F::PAY), false);

        $this->assertTrue($this->sentTo('GET', '/payments', fn (Request $r) => str_contains($r->url(), 'filter%5BcashSessionId%5D='.F::SESSION)));
    }

    public function test_property_csv_export_keeps_money_as_exact_strings(): void
    {
        $this->api();
        $res = $this->signIn(['report.view'])->get('/reports?date=2026-09-23&format=csv');

        $res->assertOk()->assertHeader('content-type', 'text/csv; charset=UTF-8');
        $csv = $res->streamedContent();
        $this->assertStringContainsString('Date,Facility,Orders,"Gross sales"', $csv);
        $this->assertStringContainsString('"Main Restaurant",10,100000.0000,0.0000,100000.0000,500.0000,1,3', $csv);
    }

    public function test_csv_neutralises_spreadsheet_formulas(): void
    {
        $this->api();
        $csv = $this->signIn(['report.view'])->get('/reports/shift/'.F::SESSION.'?format=csv')->streamedContent();

        $this->assertStringContainsString("'=1+1", $csv);
        $this->assertStringContainsString("'=HYPERLINK", $csv);
        $this->assertStringNotContainsString(',=1+1', $csv);
    }

    public function test_missing_report_endpoint_degrades(): void
    {
        $this->api(['GET /reports/revenue' => [501, []]]);
        $this->signIn(['report.view'])->get('/reports')->assertOk()->assertSee('Period revenue is not available: this API build', false)->assertSee('Main Restaurant');
    }

    public function test_invalid_dates_fall_back_to_today(): void
    {
        $this->api();
        $this->signIn(['report.view'])->get('/reports?date=not-a-date&from=%27%3Bdrop')->assertOk();
        Http::assertNotSent(fn (Request $r) => str_contains($r->url(), 'drop'));
    }
}
