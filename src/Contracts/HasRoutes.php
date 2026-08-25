<?php

declare(strict_types=1);

namespace Gusmanwidodo\AuthKit\Contracts;

/**
 * Plugins that register HTTP endpoints implement this.
 *
 * Paths returned here are loaded inside a route group prefixed with the
 * package prefix (default: `auth-kit`) so each plugin lives under
 * `/auth-kit/<plugin-id>/...` and cannot collide with app routes.
 */
interface HasRoutes
{
    /**
     * Absolute paths to route files to load.
     *
     * @return list<string>
     */
    public function routePaths(): array;
}
