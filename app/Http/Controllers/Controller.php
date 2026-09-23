<?php

namespace App\Http\Controllers;

use App\Auth\StaffSession;
use App\Services\R007Api\ApiResponse;
use App\Services\R007Api\R007ApiClient;
use Illuminate\Http\RedirectResponse;

abstract class Controller
{
    public function __construct(protected R007ApiClient $api, protected StaffSession $staff) {}

    /**
     * Redirect after a write, making an accepted-but-not-yet-approved outcome
     * (HTTP 202 / PENDING_APPROVAL) impossible to mistake for "done".
     */
    protected function done(ApiResponse $res, string $route, string $successMessage, string $pendingMessage, array $params = []): RedirectResponse
    {
        $redirect = redirect()->route($route, $params);

        if ($res->isPendingApproval()) {
            $ref = $res->approvalId();

            return $redirect->with('pending', $pendingMessage.($ref ? " Reference {$ref}." : ''))
                ->with('pending_link', $this->staff->canApproveAnything() ? route('approvals') : null);
        }

        return $redirect->with('success', $successMessage);
    }

    /** @param  array<string, mixed>  $data */
    protected function only(array $data, array $keys): array
    {
        return array_filter(array_intersect_key($data, array_flip($keys)), fn ($v) => $v !== null && $v !== '');
    }
}
