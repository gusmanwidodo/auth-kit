<?php

declare(strict_types=1);

namespace Gusmanwidodo\AuthKit\Exceptions;

use RuntimeException;

class DuplicatePluginException extends RuntimeException
{
    public static function forId(string $id): self
    {
        return new self("An Auth-Kit plugin with id [{$id}] is already registered.");
    }
}
