<?php

declare(strict_types=1);

namespace Gusmanwidodo\AuthKit;

/**
 * Mutable context passed through the before/after hook pipeline.
 *
 * Plugins read/modify payload data and may short-circuit remaining hooks
 * with stop(). Mirrors the `{ context: ctx }` pattern from better-auth.
 */
class HookContext
{
    private bool $stopped = false;

    /**
     * @param array<string, mixed> $payload
     */
    public function __construct(
        public readonly string $event,
        private array $payload = [],
    ) {
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->payload[$key] ?? $default;
    }

    public function set(string $key, mixed $value): static
    {
        $this->payload[$key] = $value;

        return $this;
    }

    /** @return array<string, mixed> */
    public function all(): array
    {
        return $this->payload;
    }

    /** Short-circuit the remaining hooks in the pipeline. */
    public function stop(): static
    {
        $this->stopped = true;

        return $this;
    }

    public function isStopped(): bool
    {
        return $this->stopped;
    }
}
