<?php

declare(strict_types=1);

namespace Gusmanwidodo\AuthKit\Contracts;

/**
 * Plugins that ship their own database migrations implement this.
 *
 * The AuthManager collects these paths and hands them to Laravel's
 * migrator so `php artisan migrate` picks them up automatically.
 */
interface HasSchema
{
    /**
     * Absolute paths to directories containing the plugin's migrations.
     *
     * @return list<string>
     */
    public function migrationPaths(): array;
}
