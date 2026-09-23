<?php

namespace App\Auth;

use Illuminate\Contracts\Session\Session;

/**
 * The signed-in staff member, read from the server-side session. Tokens and
 * the permission set never reach the browser. Permissions come from the API
 * (/auth/me) and only drive what the UI OFFERS; the API still authorises every
 * call, so a hidden button is a convenience, never the control.
 */
class StaffSession
{
    public const K_TOKEN = 'r007.api_token';

    public const K_REFRESH = 'r007.refresh_token';

    public const K_STAFF = 'r007.staff';

    public const K_SESSION = 'r007.session';

    public const K_ME_AT = 'r007.me_checked_at';

    public const K_MFA_PENDING = 'r007.mfa_pending';

    public function __construct(private readonly Session $session) {}

    public function check(): bool
    {
        return is_string($this->session->get(self::K_TOKEN)) && is_array($this->session->get(self::K_STAFF));
    }

    /** @return array<string, mixed> */
    public function staff(): array
    {
        return (array) $this->session->get(self::K_STAFF, []);
    }

    public function name(): string
    {
        return (string) ($this->staff()['displayName'] ?? 'Staff');
    }

    public function id(): ?string
    {
        return $this->staff()['id'] ?? null;
    }

    /** @return list<string> */
    public function roles(): array
    {
        return array_values((array) ($this->staff()['roles'] ?? []));
    }

    /** @return list<string> */
    public function permissions(): array
    {
        return array_values((array) ($this->staff()['permissions'] ?? []));
    }

    public function can(string $permission): bool
    {
        $perms = $this->permissions();

        return in_array('*', $perms, true) || in_array($permission, $perms, true);
    }

    /** True when the staff member holds at least one of the permissions. */
    public function canAny(string ...$permissions): bool
    {
        foreach ($permissions as $p) {
            if ($this->can($p)) {
                return true;
            }
        }

        return false;
    }

    /** Holds any "*.approve" permission (sees the approvals queue). */
    public function canApproveAnything(): bool
    {
        foreach ($this->permissions() as $p) {
            if ($p === '*' || str_ends_with($p, '.approve')) {
                return true;
            }
        }

        return false;
    }

    public function mfaPending(): bool
    {
        return (bool) $this->session->get(self::K_MFA_PENDING, false);
    }

    public function meStale(): bool
    {
        $at = (int) $this->session->get(self::K_ME_AT, 0);

        return time() - $at > (int) config('r007.me_ttl', 300);
    }

    public function sessionId(): ?string
    {
        return $this->session->get(self::K_SESSION)['id'] ?? null;
    }

    /** @param  array<string, mixed>  $staff */
    public function store(string $access, string $refresh, array $staff, array $session): void
    {
        $this->session->put(self::K_TOKEN, $access);
        $this->session->put(self::K_REFRESH, $refresh);
        $this->session->put(self::K_STAFF, $staff);
        $this->session->put(self::K_SESSION, $session);
        $this->session->put(self::K_ME_AT, time());
    }

    /** @param  array<string, mixed>  $staff */
    public function updateStaff(array $staff, ?array $session = null): void
    {
        $this->session->put(self::K_STAFF, $staff);
        if ($session !== null) {
            $this->session->put(self::K_SESSION, $session);
        }
        $this->session->put(self::K_ME_AT, time());
    }

    public function setMfaPending(bool $pending): void
    {
        $this->session->put(self::K_MFA_PENDING, $pending);
    }

    public function clear(): void
    {
        foreach ([self::K_TOKEN, self::K_REFRESH, self::K_STAFF, self::K_SESSION, self::K_ME_AT, self::K_MFA_PENDING] as $k) {
            $this->session->forget($k);
        }
    }
}
