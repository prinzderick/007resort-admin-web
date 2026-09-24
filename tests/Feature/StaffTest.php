<?php

namespace Tests\Feature;

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\Support\Fixtures as F;
use Tests\TestCase;

class StaffTest extends TestCase
{
    private const PERMS = ['staff.manage', 'role_assignment.manage', 'attendance.view', 'audit.view', 'staff.clock_correction.approve', 'device.register'];

    private function member(array $o = []): array
    {
        return $o + ['id' => F::STAFF, 'displayName' => 'Bisi Lawal', 'staffNumber' => 'S-0005', 'status' => 'ACTIVE', 'email' => 'bisi@example.test', 'phone' => '+2348000', 'rowVersion' => 3, 'firstName' => 'Bisi', 'lastName' => 'Lawal', 'hasPassword' => false, 'hasPin' => true, 'hasNfcCard' => true];
    }

    private function api(array $o = []): void
    {
        $this->fakeApi($o + [
            'GET /staff' => F::page([$this->member()]),
            'GET /staff/*/role-assignments' => F::page([['id' => 'as1', 'staffId' => F::STAFF, 'roleId' => 'role-1', 'scopeType' => 'FACILITY', 'scopeId' => F::FAC, 'grantedAt' => '2026-09-01T00:00:00.000000Z', 'revokedAt' => null]]),
            'GET /staff/*' => [200, $this->member(), ['ETag' => '"3"']],
            'GET /roles' => F::page([['id' => 'role-1', 'code' => 'cashier', 'name' => 'Cashier', 'permissions' => ['payment.take']]]),
            'GET /organization/facilities' => F::facilities(),
            'GET /organization/site' => ['id' => 'site-1', 'name' => '007'],
            'GET /devices' => F::page([['id' => 'd1', 'name' => 'POS-01', 'kind' => 'POS_TERMINAL', 'status' => 'ACTIVE', 'checkout' => ['staffId' => F::STAFF, 'facilityId' => F::FAC, 'checkedOutAt' => '2026-09-23T07:00:00.000000Z', 'checkedInAt' => null]],
                ['id' => 'd2', 'name' => 'POS-02', 'kind' => 'POS_TERMINAL', 'status' => 'ACTIVE', 'checkout' => ['staffId' => 'someone-else', 'facilityId' => F::FAC, 'checkedOutAt' => '2026-09-23T07:00:00.000000Z', 'checkedInAt' => null]]]),
            'GET /audit' => F::page([['id' => 'au1', 'seq' => 7, 'occurredAt' => '2026-09-23T08:00:00.000000Z', 'actorName' => 'Ngozi', 'action' => 'staff.update', 'entityType' => 'staff', 'entityId' => F::STAFF, 'oldValue' => ['status' => 'ACTIVE'], 'newValue' => ['status' => 'SUSPENDED'], 'hash' => 'abc']]),
        ]);
    }

    public function test_directory_lists_staff_with_sign_in_methods(): void
    {
        $this->api();
        $this->signIn(self::PERMS)->get('/staff?q=bisi&status=ACTIVE')->assertOk()->assertSee('Bisi Lawal')->assertSee('S-0005')->assertSee('PIN, NFC card');
        $this->assertTrue($this->sentTo('GET', '/staff', fn (Request $r) => str_contains($r->url(), 'q=bisi') && str_contains($r->url(), 'filter%5Bstatus%5D=ACTIVE')));
    }

    public function test_create_staff_goes_to_the_api_then_to_the_new_profile(): void
    {
        $this->api(['POST /staff' => [201, $this->member()]]);
        $this->signIn(self::PERMS)->post('/staff', ['staffNumber' => 'S-0005', 'firstName' => 'Bisi', 'lastName' => 'Lawal', 'email' => 'bisi@example.test'])->assertRedirect('/staff/'.F::STAFF);

        Http::assertSent(fn (Request $r) => $r->method() === 'POST' && str_ends_with($r->url(), '/staff') && $r['staffNumber'] === 'S-0005' && ! isset($r['phone']));
    }

    public function test_create_validation_error_from_the_api_maps_to_fields(): void
    {
        $this->api(['POST /staff' => [422, ['type' => 'x', 'title' => 'Validation failed', 'status' => 422, 'detail' => 'Staff number already exists.', 'code' => 'validation_failed', 'errors' => ['staffNumber' => ['Already taken.']]]]]);
        $this->signIn(self::PERMS)->from('/staff')->post('/staff', ['staffNumber' => 'S-1', 'firstName' => 'A', 'lastName' => 'B'])->assertRedirect('/staff')->assertSessionHasErrors('staffNumber');
    }

    public function test_profile_shows_roles_device_assignments_and_audit(): void
    {
        $this->api();
        $this->signIn(self::PERMS)->get('/staff/'.F::STAFF)->assertOk()
            ->assertSee('Bisi Lawal')->assertSee('Cashier')->assertSee('POS-01')->assertDontSee('POS-02')->assertSee('staff.update')->assertSee('NFC card');
    }

    public function test_update_uses_if_match_from_the_api_etag(): void
    {
        $this->api(['PATCH /staff/*' => $this->member(['status' => 'SUSPENDED', 'rowVersion' => 4])]);
        $this->signIn(self::PERMS)->patch('/staff/'.F::STAFF, ['firstName' => 'Bisi', 'lastName' => 'Lawal', 'status' => 'SUSPENDED', 'etag' => '"3"'])->assertRedirect('/staff/'.F::STAFF);

        Http::assertSent(fn (Request $r) => $r->method() === 'PATCH' && $r->hasHeader('If-Match', '"3"') && $r['status'] === 'SUSPENDED' && ! isset($r['etag']));
    }

    public function test_concurrent_edit_conflict_is_reported(): void
    {
        $this->api(['PATCH /staff/*' => $this->problem(412, 'concurrency_conflict', 'Someone else changed this record.')]);
        $this->signIn(self::PERMS)->from('/staff/x')->patch('/staff/'.F::STAFF, ['firstName' => 'B', 'lastName' => 'L', 'status' => 'ACTIVE', 'etag' => '"1"'])->assertSessionHas('error', fn ($m) => str_contains($m, 'Someone else changed this record.'));
    }

    public function test_grant_and_revoke_role(): void
    {
        $this->api(['POST /staff/*/role-assignments' => [201, ['id' => 'as2']], 'DELETE /staff/*/role-assignments/*' => [204, []]]);
        $this->signIn(self::PERMS);

        $this->post('/staff/'.F::STAFF.'/roles', ['roleId' => F::FAC, 'scopeType' => 'FACILITY', 'scopeId' => F::FAC])->assertSessionHas('success', 'Role granted.');
        $this->delete('/staff/'.F::STAFF.'/roles/'.F::FAC)->assertSessionHas('success', 'Role revoked.');
        Http::assertSent(fn (Request $r) => $r->method() === 'DELETE' && str_contains($r->url(), '/role-assignments/'.F::FAC));
    }

    public function test_role_grant_requires_role_assignment_permission(): void
    {
        $this->api();
        $this->signIn(['staff.manage'])->post('/staff/'.F::STAFF.'/roles', ['roleId' => F::FAC, 'scopeType' => 'SITE', 'scopeId' => F::FAC])->assertForbidden();
    }

    public function test_nfc_card_assignment_and_removal(): void
    {
        $this->api(['PUT /staff/*/credentials/nfc-card' => [204, []], 'DELETE /staff/*/credentials/nfc-card' => [204, []]]);
        $this->signIn(self::PERMS);

        $this->put('/staff/'.F::STAFF.'/credentials/nfc-card', ['cardUid' => '04:A2:3B:9C'])->assertSessionHas('success', 'NFC card assigned.');
        Http::assertSent(fn (Request $r) => $r->method() === 'PUT' && str_ends_with($r->url(), '/credentials/nfc-card') && $r['cardUid'] === '04:A2:3B:9C');
        $this->delete('/staff/'.F::STAFF.'/credentials/nfc-card')->assertSessionHas('success', 'NFC card removed.');
    }

    public function test_password_and_pin_rules_are_checked_before_the_api(): void
    {
        $this->api();
        $this->signIn(self::PERMS);

        $this->put('/staff/'.F::STAFF.'/credentials/password', ['password' => 'short'])->assertSessionHasErrors('password');
        $this->put('/staff/'.F::STAFF.'/credentials/pin', ['pin' => '12'])->assertSessionHasErrors('pin');
        $this->put('/staff/'.F::STAFF.'/credentials/nfc-card', ['cardUid' => 'bad uid!'])->assertSessionHasErrors('cardUid');
        $this->put('/staff/'.F::STAFF.'/credentials/unknown', ['x' => 1])->assertNotFound();
        Http::assertNothingSent();
    }

    public function test_credentials_are_never_echoed_back(): void
    {
        $this->api(['PUT /staff/*/credentials/password' => [204, []]]);
        $html = $this->signIn(self::PERMS)->followingRedirects()->put('/staff/'.F::STAFF.'/credentials/password', ['password' => 'CorrectHorse-Battery1'])->getContent();
        $this->assertStringNotContainsString('CorrectHorse-Battery1', $html);
    }

    public function test_attendance_list_and_correction_decisions(): void
    {
        $this->api([
            'GET /attendance' => F::page([['id' => 'at1', 'staffId' => F::STAFF, 'staffName' => 'Bisi Lawal', 'workDate' => '2026-09-23', 'clockIn' => '2026-09-23T06:00:00.000000Z', 'clockOut' => null, 'minutesWorked' => null, 'status' => 'OPEN']]),
            'GET /attendance/corrections' => F::page([['id' => 'cor1', 'staffId' => F::STAFF, 'workDate' => '2026-09-22', 'clockIn' => null, 'clockOut' => '2026-09-22T17:00:00.000000Z', 'reason' => 'Forgot to clock out', 'status' => 'PENDING']]),
            'POST /attendance/corrections/cor1/approve' => ['id' => 'cor1', 'status' => 'APPROVED'],
        ]);
        $this->signIn(self::PERMS);

        $this->get('/staff/attendance')->assertOk()->assertSee('Bisi Lawal')->assertSee('Forgot to clock out')->assertSee('Approve');
        $this->post('/staff/attendance/corrections/cor1/approve')->assertSessionHas('success', 'Correction approved.');
        $this->post('/staff/attendance/corrections/cor1/bogus')->assertNotFound();
    }

    public function test_attendance_csv(): void
    {
        $this->api(['GET /attendance' => F::page([['id' => 'at1', 'staffName' => 'Bisi Lawal', 'workDate' => '2026-09-23', 'clockIn' => '2026-09-23T06:00:00.000000Z', 'clockOut' => null, 'minutesWorked' => 470, 'status' => 'CLOSED', 'source' => 'BIOMETRIC']]), 'GET /attendance/corrections' => F::page([])]);
        $this->assertStringContainsString('"Bisi Lawal"', $this->signIn(self::PERMS)->get('/staff/attendance?format=csv')->streamedContent());
    }

    public function test_audit_trail_viewer_with_filters(): void
    {
        $this->api();
        $this->signIn(self::PERMS)->get('/system/audit?action=staff&entityType=staff&from=2026-09-01')->assertOk()->assertSee('staff.update')->assertSee('SUSPENDED');
        $this->assertTrue($this->sentTo('GET', '/audit', fn (Request $r) => str_contains($r->url(), 'action=staff') && str_contains($r->url(), 'entityType=staff')));
    }

    public function test_audit_needs_its_permission(): void
    {
        $this->api();
        $this->signIn(['staff.manage'])->get('/system/audit')->assertForbidden();
    }
}
