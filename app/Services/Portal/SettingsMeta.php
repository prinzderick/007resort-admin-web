<?php

namespace App\Services\Portal;

use App\Auth\StaffSession;
use App\Services\R007Api\R007ApiClient;
use App\Support\Fetch;

/** "Last changed by/when" for one entity, read from the audit trail (needs audit.view). One lookup per entity per request. */
class SettingsMeta
{
    /** @var array<string, array<string, mixed>|null> */
    private array $memo = [];

    public function __construct(private readonly R007ApiClient $api, private readonly StaffSession $session, private readonly Directory $dir) {}

    /** @return array{at: string, by: string, action: string}|null */
    public function last(?string $entityType, ?string $entityId): ?array
    {
        if (! $this->session->can('audit.view') || ! $entityType) {
            return null;
        }
        $key = $entityType.'|'.$entityId;
        if (array_key_exists($key, $this->memo)) {
            return $this->memo[$key];
        }
        $q = ['entityType' => $entityType, 'limit' => 1, 'order' => 'desc'] + ($entityId ? ['entityId' => $entityId] : []);
        $res = Fetch::of(fn () => $this->api->get('audit', $q), ['GET', '/audit']);
        $row = $res->items()[0] ?? null;
        if (! is_array($row)) {
            return $this->memo[$key] = null;
        }
        $actor = $row['actorStaffId'] ?? null;

        return $this->memo[$key] = [
            'at' => (string) ($row['occurredAt'] ?? ''),
            'by' => $actor ? ($this->dir->staffNames()[$actor] ?? Directory::short($actor)) : 'the system',
            'action' => (string) ($row['action'] ?? 'changed'),
        ];
    }
}
