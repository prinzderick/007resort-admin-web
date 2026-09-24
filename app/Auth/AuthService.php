<?php

namespace App\Auth;

use App\Services\R007Api\R007ApiClient;
use App\Services\R007Api\R007ApiException;

/** Staff sign-in/out and permission refresh, all through the API. */
class AuthService
{
    public function __construct(
        private readonly R007ApiClient $api,
        private readonly StaffSession $staff,
        private readonly Mfa $mfa,
    ) {}

    /**
     * Password login. Returns true when MFA is still required before the
     * session may be used.
     *
     * @throws R007ApiException invalid_credentials, account_locked, unreachable ...
     */
    public function login(string $identifier, string $password): bool
    {
        $this->staff->clear();

        $result = $this->api->post('auth/staff/login', [
            'credentialType' => 'PASSWORD',
            'identifier' => $identifier,
            'secret' => $password,
        ]);

        $this->staff->store(
            (string) $result['accessToken'],
            (string) ($result['refreshToken'] ?? ''),
            (array) ($result['staff'] ?? []),
            (array) ($result['session'] ?? []),
        );

        // Navigation is driven by /auth/me, the API's view of what this person may do.
        $this->refreshMe();

        $mfa = $this->mfa->requiredFor($this->staff);
        $this->staff->setMfaPending($mfa);

        return $mfa;
    }

    public function refreshMe(): void
    {
        $me = $this->api->get('auth/me');

        if (isset($me['staff']) && is_array($me['staff'])) {
            $this->staff->updateStaff($me['staff'], isset($me['session']) ? (array) $me['session'] : null);
        }
    }

    public function logout(): void
    {
        if ($this->staff->check()) {
            try {
                $this->api->post('auth/staff/logout', []);
            } catch (R007ApiException) {
                // Token may already be dead; local session is cleared regardless.
            }
        }

        $this->staff->clear();
    }
}
