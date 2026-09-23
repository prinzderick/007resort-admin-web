<?php

namespace App\Support;

use App\Services\R007Api\R007ApiException;
use Throwable;

/**
 * Result of one API read that a screen can render without blowing up:
 * a screen composed of several reads keeps working when one is unavailable.
 *
 * state: ok | pending (endpoint not in the contract) | missing (contract has it,
 *        this API build does not) | forbidden | unreachable | error
 */
final class Fetch
{
    public function __construct(
        public readonly mixed $data,
        public readonly string $state = 'ok',
        public readonly ?string $message = null,
    ) {}

    /**
     * @param  callable(): mixed  $call
     * @param  array{0: string, 1: string}|null  $endpoint  [method, path template] checked against the contract first
     */
    public static function of(callable $call, ?array $endpoint = null, mixed $default = null): self
    {
        if ($endpoint !== null && ! Contract::has($endpoint[0], $endpoint[1])) {
            return new self($default, 'pending', "{$endpoint[0]} {$endpoint[1]} is not in the API contract yet.");
        }

        try {
            return new self($call());
        } catch (R007ApiException $e) {
            if ($e->isUnauthenticated()) {
                throw $e; // handled globally: back to login
            }

            $state = match (true) {
                $e->isEndpointMissing() => 'missing',
                $e->isForbidden() => 'forbidden',
                $e->isUnreachable() => 'unreachable',
                default => 'error',
            };

            return new self($default, $state, $e->detail ?: $e->title);
        } catch (Throwable $e) {
            report($e);

            return new self($default, 'error', 'Unexpected error while reading from the API.');
        }
    }

    public function ok(): bool
    {
        return $this->state === 'ok';
    }

    /** @return array<mixed> */
    public function items(): array
    {
        return $this->ok() && is_array($this->data) ? ($this->data['items'] ?? $this->data) : [];
    }

    public function next(): ?string
    {
        return $this->ok() && is_array($this->data) ? ($this->data['nextCursor'] ?? null) : null;
    }
}
