<?php

declare(strict_types=1);

namespace Gusmanwidodo\AuthKit;

use Gusmanwidodo\AuthKit\Contracts\AuthPlugin;
use Gusmanwidodo\AuthKit\Contracts\HasHooks;

/**
 * Central orchestrator. Boots registered plugins and runs the hook pipeline.
 *
 * Route and migration collection happen in the ServiceProvider (which has
 * access to the router/migrator); the manager owns plugin lifecycle + hooks,
 * the runtime surface plugins actually call into.
 */
class AuthManager
{
    private bool $booted = false;

    public function __construct(
        private readonly PluginRegistry $registry,
    ) {
    }

    public function registry(): PluginRegistry
    {
        return $this->registry;
    }

    /** Boot every registered plugin exactly once. */
    public function boot(): void
    {
        if ($this->booted) {
            return;
        }

        foreach ($this->registry->all() as $plugin) {
            $plugin->boot();
        }

        $this->booted = true;
    }

    public function isBooted(): bool
    {
        return $this->booted;
    }

    /**
     * Run all `before` hooks registered by plugins for the given event.
     *
     * @param array<string, mixed> $payload
     */
    public function runBefore(string $event, array $payload = []): HookContext
    {
        return $this->runPipeline($event, $payload, before: true);
    }

    /**
     * Run all `after` hooks registered by plugins for the given event.
     *
     * @param array<string, mixed> $payload
     */
    public function runAfter(string $event, array $payload = []): HookContext
    {
        return $this->runPipeline($event, $payload, before: false);
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function runPipeline(string $event, array $payload, bool $before): HookContext
    {
        $context = new HookContext($event, $payload);

        foreach ($this->registry->all() as $plugin) {
            if (! $plugin instanceof HasHooks) {
                continue;
            }

            $hooks = $before ? $plugin->beforeHooks() : $plugin->afterHooks();

            if (! isset($hooks[$event])) {
                continue;
            }

            ($hooks[$event])($context);

            if ($context->isStopped()) {
                break;
            }
        }

        return $context;
    }
}
