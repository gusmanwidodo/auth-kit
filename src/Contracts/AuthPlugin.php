<?php

declare(strict_types=1);

namespace Gusmanwidodo\AuthKit\Contracts;

/**
 * The base contract every Auth-Kit plugin must implement.
 *
 * Inspired by better-auth's BetterAuthPlugin: the only hard requirement is a
 * unique `id`. Everything else (schema, routes, hooks) is opt-in via the
 * companion interfaces (HasSchema, HasHooks, HasRoutes).
 */
interface AuthPlugin
{
    /**
     * Unique, stable identifier for the plugin (kebab-case).
     * Used to namespace routes, config, and to detect collisions.
     */
    public function id(): string;

    /**
     * Called once when the plugin is booted by the AuthManager.
     * Use for wiring that needs the fully-built application container.
     */
    public function boot(): void;
}
