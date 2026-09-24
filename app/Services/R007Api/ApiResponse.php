<?php

namespace App\Services\R007Api;

/**
 * Successful API response (2xx). Failures are raised as R007ApiException.
 */
final class ApiResponse
{
    /**
     * @param  array<mixed>  $body
     * @param  array<string, string>  $headers  lower-cased header name => first value
     */
    public function __construct(
        public readonly int $status,
        public readonly array $body,
        public readonly array $headers = [],
    ) {}

    /**
     * True when the API accepted the request but a supervisor must still
     * approve it (HTTP 202, or a resource whose status is PENDING_APPROVAL).
     * The UI must show this as "awaiting approval", never as "done".
     */
    public function isPendingApproval(): bool
    {
        return $this->status === 202
            || ($this->body['status'] ?? null) === 'PENDING_APPROVAL';
    }

    public function approvalId(): ?string
    {
        $id = $this->body['approval']['id'] ?? $this->body['approvalId'] ?? null;

        return is_string($id) ? $id : null;
    }

    public function etag(): ?string
    {
        return $this->headers['etag'] ?? null;
    }
}
