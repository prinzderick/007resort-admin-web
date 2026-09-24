<?php

namespace App\Services\Portal;

use App\Auth\StaffSession;
use App\Services\R007Api\R007ApiClient;
use App\Support\Fetch;

/**
 * Resolves ids the API returns (staff, facilities, devices) to display names for the
 * screens that only get ids (audit actor, attendance corrections, checked-out tablets).
 * Only reads what the signed-in account may read; anything it cannot resolve falls back
 * to a short id, never an error.
 */
class Directory
{
    /** @var array<string, string>|null */
    private ?array $staff = null;

    public function __construct(private readonly R007ApiClient $api, private readonly StaffSession $session) {}

    /** @return array<string, string> staff id => display name */
    public function staffNames(): array
    {
        if ($this->staff === null) {
            $this->staff = [];
            if ($this->session->can('staff.manage')) {
                $rows = Fetch::of(fn () => $this->api->get('staff', ['limit' => 200]), ['GET', '/staff']);
                foreach ($rows->items() as $m) {
                    if (is_array($m) && isset($m['id'])) {
                        $this->staff[(string) $m['id']] = (string) ($m['displayName'] ?? $m['staffNumber'] ?? $m['id']);
                    }
                }
            }
        }

        return $this->staff;
    }

    public static function short(?string $id): string
    {
        return $id === null || $id === '' ? '-' : substr($id, 0, 8);
    }
}
