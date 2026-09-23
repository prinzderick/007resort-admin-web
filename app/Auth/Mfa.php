<?php

namespace App\Auth;

use App\Services\R007Api\R007ApiClient;
use App\Services\R007Api\R007ApiException;
use App\Support\Contract;

/**
 * MFA hook for Owner/Manager/Accounts/IT on the REMOTE (cloud) instance
 * (architecture 06 s4, 17 s1). The API contract v1 has no MFA endpoint yet, so:
 *
 *  - required only when R007_INSTANCE=cloud AND R007_MFA_ENFORCE=true AND the
 *    staff member holds a sensitive permission (config r007.mfa.permissions);
 *  - verification calls POST /auth/staff/mfa/verify when the contract has it;
 *  - if enforcement is on but the API cannot verify, we FAIL CLOSED
 *    (session stays locked) rather than silently skipping the second factor;
 *  - mock mode accepts the fixed code 000000.
 */
class Mfa
{
    public const VERIFY = ['POST', '/auth/staff/mfa/verify'];

    public function __construct(private readonly R007ApiClient $api) {}

    public function requiredFor(StaffSession $staff): bool
    {
        if (config('r007.instance') !== 'cloud' || ! config('r007.mfa.enforce')) {
            return false;
        }

        return $staff->canAny(...(array) config('r007.mfa.permissions', []));
    }

    public function available(): bool
    {
        return $this->api->isMock() || Contract::has(...self::VERIFY);
    }

    public function verify(string $code): bool
    {
        if ($this->api->isMock()) {
            return $code === '000000';
        }

        if (! Contract::has(...self::VERIFY)) {
            return false; // fail closed
        }

        try {
            $this->api->post('auth/staff/mfa/verify', ['code' => $code]);

            return true;
        } catch (R007ApiException $e) {
            if ($e->status >= 500 || $e->isUnreachable()) {
                throw $e;
            }

            return false;
        }
    }
}
