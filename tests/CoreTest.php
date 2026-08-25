<?php

declare(strict_types=1);

use Gusmanwidodo\AuthKit\AuthManager;
use Gusmanwidodo\AuthKit\Contracts\AuthPlugin;
use Gusmanwidodo\AuthKit\Contracts\HasHooks;
use Gusmanwidodo\AuthKit\Exceptions\DuplicatePluginException;
use Gusmanwidodo\AuthKit\HookContext;
use Gusmanwidodo\AuthKit\PluginRegistry;

/** Minimal in-test plugin. */
function makePlugin(string $id, array $before = [], array $after = []): AuthPlugin
{
    return new class ($id, $before, $after) implements AuthPlugin, HasHooks {
        public function __construct(
            private string $pid,
            private array $before,
            private array $after,
        ) {
        }

        public function id(): string
        {
            return $this->pid;
        }

        public function boot(): void
        {
        }

        public function beforeHooks(): array
        {
            return $this->before;
        }

        public function afterHooks(): array
        {
            return $this->after;
        }
    };
}

it('registers and resolves plugins by id', function () {
    $registry = new PluginRegistry();
    $registry->register(makePlugin('alpha'));

    expect($registry->has('alpha'))->toBeTrue()
        ->and($registry->get('alpha'))->not->toBeNull()
        ->and($registry->count())->toBe(1);
});

it('rejects duplicate plugin ids', function () {
    $registry = new PluginRegistry();
    $registry->register(makePlugin('dup'));

    $registry->register(makePlugin('dup'));
})->throws(DuplicatePluginException::class);

it('runs before hooks in registration order', function () {
    $registry = new PluginRegistry();
    $order = [];

    $registry->register(makePlugin('one', [
        'evt' => function (HookContext $c) use (&$order) {
            $order[] = 'one';
        },
    ]));
    $registry->register(makePlugin('two', [
        'evt' => function (HookContext $c) use (&$order) {
            $order[] = 'two';
        },
    ]));

    $manager = new AuthManager($registry);
    $manager->runBefore('evt');

    expect($order)->toBe(['one', 'two']);
});

it('short-circuits the pipeline when a hook stops it', function () {
    $registry = new PluginRegistry();
    $ran = [];

    $registry->register(makePlugin('first', [
        'evt' => function (HookContext $c) use (&$ran) {
            $ran[] = 'first';
            $c->set('blocked', true)->stop();
        },
    ]));
    $registry->register(makePlugin('second', [
        'evt' => function (HookContext $c) use (&$ran) {
            $ran[] = 'second';
        },
    ]));

    $manager = new AuthManager($registry);
    $context = $manager->runBefore('evt');

    expect($ran)->toBe(['first'])
        ->and($context->isStopped())->toBeTrue()
        ->and($context->get('blocked'))->toBeTrue();
});

it('boots each plugin exactly once', function () {
    $registry = new PluginRegistry();

    $plugin = new class implements AuthPlugin {
        public int $bootCount = 0;

        public function id(): string
        {
            return 'boot-counter';
        }

        public function boot(): void
        {
            $this->bootCount++;
        }
    };

    $registry->register($plugin);

    $manager = new AuthManager($registry);
    $manager->boot();
    $manager->boot(); // second call must be a no-op

    expect($manager->isBooted())->toBeTrue()
        ->and($plugin->bootCount)->toBe(1);
});
