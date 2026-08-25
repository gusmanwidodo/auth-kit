<?php

declare(strict_types=1);

namespace Gusmanwidodo\AuthKit;

use Gusmanwidodo\AuthKit\Contracts\AuthPlugin;
use Gusmanwidodo\AuthKit\Exceptions\DuplicatePluginException;

/**
 * Holds the set of registered plugins, keyed by their unique id.
 *
 * Registration is collision-safe: registering two plugins with the same id
 * throws, matching better-auth's rule that plugin ids must be unique.
 */
class PluginRegistry
{
    /** @var array<string, AuthPlugin> */
    private array $plugins = [];

    public function register(AuthPlugin $plugin): void
    {
        $id = $plugin->id();

        if (isset($this->plugins[$id])) {
            throw DuplicatePluginException::forId($id);
        }

        $this->plugins[$id] = $plugin;
    }

    public function has(string $id): bool
    {
        return isset($this->plugins[$id]);
    }

    public function get(string $id): ?AuthPlugin
    {
        return $this->plugins[$id] ?? null;
    }

    /** @return array<string, AuthPlugin> */
    public function all(): array
    {
        return $this->plugins;
    }

    public function count(): int
    {
        return count($this->plugins);
    }
}
