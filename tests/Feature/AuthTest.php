<?php

namespace Tests\Feature;

use App\Auth\StaffSession;
use App\Services\R007Api\R007ApiClient;
use App\Support\Contract;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AuthTest extends TestCase
{
    private function loginResponses(array $permissions = ['report.view'], ?array $me = null): array
    {
        $staff = ['id' => 's1', 'displayName' => 'Ada Owner', 'roles' => ['owner'], 'permissions' => ['stale.from.login']];

        return [
            'POST /auth/staff/login' => ['accessToken' => 'acc-1', 'refreshToken' => 'ref-1', 'expiresInSeconds' => 900, 'staff' => $staff, 'session' => ['id' => 'sess-1']],
            'GET /auth/me' => $me ?? ['staff' => ['permissions' => $permissions] + $staff, 'session' => ['id' => 'sess-1'], 'device' => null],
            'POST /auth/staff/logout' => [204, []],
            '* /*' => [404, []],
        ];
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get('/')->assertRedirect('/login');
        $this->get('/reports')->assertRedirect('/login');
    }

    public function test_login_goes_through_the_api_and_keeps_tokens_server_side(): void
    {
        $this->fakeApi($this->loginResponses(['report.view', 'payment.view']));

        $this->post('/login', ['identifier' => 'S-0001', 'password' => 'secret-pass'])->assertRedirect('/');

        // the API saw a PASSWORD credential with an Idempotency-Key
        Http::assertSent(fn (Request $r) => $r->method() === 'POST' && str_ends_with($r->url(), '/auth/staff/login')
            && $r['credentialType'] === 'PASSWORD' && $r['identifier'] === 'S-0001' && $r['secret'] === 'secret-pass' && $r->hasHeader('Idempotency-Key'));

        // tokens and permissions (from /auth/me, not from the login body) live in the session only
        $this->assertSame('acc-1', session(StaffSession::K_TOKEN));
        $this->assertSame('ref-1', session(StaffSession::K_REFRESH));
        $this->assertSame(['report.view', 'payment.view'], session(StaffSession::K_STAFF)['permissions']);
    }

    public function test_tokens_never_reach_the_browser(): void
    {
        $this->fakeApi($this->loginResponses(['report.view']) + []);
        $res = $this->followingRedirects()->post('/login', ['identifier' => 'S-0001', 'password' => 'secret-pass']);

        $body = $res->getContent();
        $this->assertStringNotContainsString('acc-1', $body);
        $this->assertStringNotContainsString('ref-1', $body);
        $this->assertStringNotContainsString('secret-pass', $body);
    }

    public function test_invalid_credentials_show_the_api_message(): void
    {
        $this->fakeApi(['POST /auth/staff/login' => $this->problem(401, 'invalid_credentials', 'Wrong staff number or password.')]);

        $this->post('/login', ['identifier' => 'x', 'password' => 'y'])->assertSessionHasErrors(['identifier' => 'Wrong staff number or password.']);
        $this->assertNull(session(StaffSession::K_TOKEN));
    }

    public function test_locked_account_is_reported_not_a_500(): void
    {
        $this->fakeApi(['POST /auth/staff/login' => $this->problem(403, 'account_locked', 'Account locked for 15 minutes.')]);

        $this->post('/login', ['identifier' => 'x', 'password' => 'y'])->assertSessionHasErrors('identifier');
    }

    public function test_unreachable_api_at_login_gives_a_clear_error_page(): void
    {
        Http::preventStrayRequests();
        Http::fake(fn () => throw new ConnectionException('cURL error 7: connection refused'));

        $this->post('/login', ['identifier' => 'x', 'password' => 'y'])->assertStatus(503)->assertSee('The API is unreachable');
    }

    public function test_logout_revokes_the_api_session_and_clears_ours(): void
    {
        $this->fakeApi(['POST /auth/staff/logout' => [204, []]]);

        $this->signIn(['report.view'])->post('/logout')->assertRedirect('/login');

        Http::assertSent(fn (Request $r) => str_ends_with($r->url(), '/auth/staff/logout') && $r->hasHeader('Authorization', 'Bearer test-access-token'));
        $this->get('/')->assertRedirect('/login');
    }

    public function test_expired_api_session_sends_the_user_back_to_login(): void
    {
        $this->fakeApi([
            'POST /auth/staff/refresh' => $this->problem(401, 'unauthenticated'),
            '* /*' => $this->problem(401, 'token_expired', 'Access token expired.'),
        ]);

        $this->signIn(['report.view', 'payment.view'])->get('/finance/payments')->assertRedirect('/login');
        $this->assertNull(session(StaffSession::K_TOKEN));
    }

    public function test_access_token_is_refreshed_once_and_the_request_retried(): void
    {
        $calls = 0;
        $this->fakeApi([
            'POST /auth/staff/refresh' => ['accessToken' => 'new-acc', 'refreshToken' => 'new-ref', 'expiresInSeconds' => 900],
            'GET /ping' => function (Request $r) use (&$calls) {
                $calls++;

                return $r->hasHeader('Authorization', 'Bearer new-acc') ? ['ok' => true] : $this->problem(401, 'token_expired');
            },
        ]);
        $this->signIn();

        $out = $this->app->make(R007ApiClient::class)->get('ping');

        $this->assertSame(['ok' => true], $out);
        $this->assertSame(2, $calls);
        $this->assertSame('new-ref', session(StaffSession::K_REFRESH));
    }

    public function test_permissions_are_refreshed_from_auth_me_when_stale(): void
    {
        $this->fakeApi([
            'GET /auth/me' => ['staff' => ['id' => 's', 'displayName' => 'Ada', 'roles' => ['x'], 'permissions' => ['payment.view']], 'session' => ['id' => 'x']],
            'GET /payments' => ['items' => []],
            'GET /organization/facilities' => ['items' => []],
        ]);

        $this->signIn(['audit.view'])->withSession([StaffSession::K_ME_AT => time() - 10_000]);
        $this->get('/finance/payments')->assertOk(); // allowed only because the fresh /auth/me granted payment.view

        $this->assertSame(['payment.view'], session(StaffSession::K_STAFF)['permissions']);
    }

    public function test_mfa_is_required_on_the_cloud_instance_for_sensitive_roles(): void
    {
        config(['r007.instance' => 'cloud', 'r007.mfa.enforce' => true]);
        $this->fakeApi($this->loginResponses(['config.manage', 'report.view']));

        $this->post('/login', ['identifier' => 'S-0001', 'password' => 'secret-pass'])->assertRedirect('/mfa');
        $this->get('/')->assertRedirect('/mfa'); // cannot use the portal until verified
    }

    public function test_mfa_fails_closed_when_the_api_cannot_verify(): void
    {
        config(['r007.instance' => 'cloud', 'r007.mfa.enforce' => true]);
        $this->fakeApi($this->loginResponses(['config.manage']));
        $this->post('/login', ['identifier' => 'S-0001', 'password' => 'secret-pass']);

        $this->post('/mfa', ['code' => '123456'])->assertSessionHasErrors('code');
        $this->get('/')->assertRedirect('/mfa');
    }

    public function test_mfa_not_required_on_the_local_instance(): void
    {
        config(['r007.instance' => 'local', 'r007.mfa.enforce' => true]);
        $this->fakeApi($this->loginResponses(['config.manage']));

        $this->post('/login', ['identifier' => 'S-0001', 'password' => 'secret-pass'])->assertRedirect('/');
    }

    public function test_non_sensitive_staff_skip_mfa_on_cloud(): void
    {
        config(['r007.instance' => 'cloud', 'r007.mfa.enforce' => true]);
        $this->fakeApi($this->loginResponses(['order.view']));

        $this->post('/login', ['identifier' => 'S-0001', 'password' => 'secret-pass'])->assertRedirect('/');
    }

    public function test_mfa_hook_verifies_when_the_contract_defines_the_endpoint(): void
    {
        config(['r007.instance' => 'cloud', 'r007.mfa.enforce' => true]);
        Contract::fake(['POST /auth/staff/mfa/verify']);
        $this->fakeApi(['POST /auth/staff/mfa/verify' => ['verified' => true]] + $this->loginResponses(['config.manage']));

        $this->post('/login', ['identifier' => 'S-0001', 'password' => 'secret-pass'])->assertRedirect('/mfa');
        $this->post('/mfa', ['code' => '123456'])->assertRedirect('/');
        $this->assertFalse(session(StaffSession::K_MFA_PENDING));
    }
}
