<?php

namespace Tests\Feature;

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\Support\Fixtures as F;
use Tests\TestCase;

class DevicesTest extends TestCase
{
    private const PERMS = ['device.register', 'device.revoke', 'attendance.device.manage'];

    private function api(array $o = []): void
    {
        $this->fakeApi($o + [
            'GET /organization/facilities' => F::facilities(),
            'GET /devices' => F::page([
                ['id' => 'd1', 'name' => 'POS-01', 'kind' => 'POS_TERMINAL', 'status' => 'ACTIVE', 'facilityId' => F::FAC, 'platform' => 'windows', 'appVersion' => '1.0.3', 'lastSeenAt' => now()->utc()->subSeconds(20)->toIso8601ZuluString(), 'checkout' => ['staffId' => 'abcdef1234', 'facilityId' => F::FAC, 'checkedOutAt' => '2026-09-23T07:00:00.000000Z', 'checkedInAt' => null]],
                ['id' => 'd2', 'name' => 'Tablet-03', 'kind' => 'MOBILE_TABLET', 'status' => 'ACTIVE', 'facilityId' => F::FAC, 'lastSeenAt' => now()->utc()->subMinutes(30)->toIso8601ZuluString(), 'checkout' => null],
                ['id' => 'd3', 'name' => 'Tablet-lost', 'kind' => 'MOBILE_TABLET', 'status' => 'REVOKED', 'facilityId' => null, 'lastSeenAt' => null, 'checkout' => null],
            ]),
            'GET /attendance/devices' => F::page([['id' => 't1', 'serialNumber' => 'ZK-0001', 'name' => 'Gate terminal', 'adapter' => 'ZKTECO_ADMS', 'status' => 'ACTIVE', 'lastSeenAt' => null]]),
        ]);
    }

    public function test_device_list_shows_status_and_flags_quiet_devices(): void
    {
        $this->api();
        $res = $this->signIn(self::PERMS)->get('/devices?status=ACTIVE')->assertOk();

        $res->assertSee('POS-01')->assertSee('Tablet-03')->assertSee('REVOKED')->assertSee('quiet')->assertSee('ZK-0001');
        $this->assertSame(1, substr_count($res->getContent(), '>quiet<'));
        $this->assertTrue($this->sentTo('GET', '/devices', fn (Request $r) => str_contains($r->url(), 'filter%5Bstatus%5D=ACTIVE')));
    }

    public function test_registration_code_is_shown_once_and_never_persisted_by_us(): void
    {
        $this->api(['POST /devices/registration-codes' => [201, ['code' => 'K7QF-2931', 'expiresAt' => now()->utc()->addMinutes(15)->toIso8601ZuluString()]]]);
        $this->signIn(self::PERMS)->followingRedirects()->post('/devices/registration-code', ['facilityId' => F::FAC])->assertOk()->assertSee('K7QF-2931')->assertSee('works once');

        Http::assertSent(fn (Request $r) => $r->method() === 'POST' && str_ends_with($r->url(), '/devices/registration-codes') && $r['facilityId'] === F::FAC);
        $this->get('/devices')->assertDontSee('K7QF-2931'); // flash, gone on the next request
    }

    public function test_revoke_device(): void
    {
        $this->api(['POST /devices/d1/revoke' => ['id' => 'd1', 'status' => 'REVOKED']]);
        $this->signIn(self::PERMS)->post('/devices/d1/revoke')->assertRedirect('/devices')->assertSessionHas('success', fn ($m) => str_contains($m, 'Device revoked'));
        Http::assertSent(fn (Request $r) => str_ends_with($r->url(), '/devices/d1/revoke') && $r->hasHeader('Idempotency-Key'));
    }

    public function test_registering_and_revoking_are_separate_permissions(): void
    {
        $this->api();
        $this->signIn(['device.register'])->post('/devices/d1/revoke')->assertForbidden();
        $this->signIn(['device.revoke'])->post('/devices/registration-code')->assertForbidden();
    }

    public function test_biometric_terminal_token_shown_once_on_create_and_rotate(): void
    {
        $this->api(['POST /attendance/devices' => [201, ['device' => ['id' => 't2'], 'deviceToken' => 'tok-create-1']], 'POST /attendance/devices/*/rotate-token' => ['device' => ['id' => 't1'], 'deviceToken' => 'tok-rotated-2'], 'POST /attendance/devices/*/status' => ['id' => 't1', 'status' => 'DISABLED']]);
        $this->signIn(self::PERMS);

        $this->followingRedirects()->post('/devices/terminals', ['serialNumber' => 'ZK-0002', 'name' => 'Spare terminal', 'adapter' => 'ZKTECO_ADMS'])->assertSee('tok-create-1');
        $this->followingRedirects()->post('/devices/terminals/t1/rotate')->assertSee('tok-rotated-2');
        $this->post('/devices/terminals/t1/status', ['status' => 'DISABLED'])->assertSessionHas('success');
        $this->post('/devices/terminals/t1/status', ['status' => 'EXPLODED'])->assertSessionHasErrors('status');
    }

    public function test_it_without_attendance_permission_does_not_see_terminals(): void
    {
        $this->api();
        $this->signIn(['device.register'])->get('/devices')->assertOk()->assertDontSee('Attendance terminals');
    }
}
