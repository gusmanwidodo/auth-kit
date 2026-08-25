<?php

declare(strict_types=1);

namespace Gusmanwidodo\AuthKit\Contracts;

use Gusmanwidodo\AuthKit\HookContext;

/**
 * Plugins that need to run logic before/after auth lifecycle events.
 *
 * Mirrors better-auth's hooks.before / hooks.after. Hooks receive a mutable
 * HookContext and may short-circuit the pipeline by calling $context->stop().
 */
interface HasHooks
{
    /**
     * Map of event name => callable(HookContext): void to run BEFORE the event.
     *
     * @return array<string, callable>
     */
    public function beforeHooks(): array;

    /**
     * Map of event name => callable(HookContext): void to run AFTER the event.
     *
     * @return array<string, callable>
     */
    public function afterHooks(): array;
}
