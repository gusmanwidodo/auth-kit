<?php

declare(strict_types=1);

namespace Gusmanwidodo\AuthKit;

use Gusmanwidodo\AuthKit\Contracts\AuthPlugin;
use Gusmanwidodo\AuthKit\Contracts\HasRoutes;
use Gusmanwidodo\AuthKit\Contracts\HasSchema;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class AuthKitServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/auth-kit.php', 'auth-kit');

        // Core singletons. Separate packages resolve AuthManager to self-register.
        $this->app->singleton(PluginRegistry::class, fn () => new PluginRegistry());
        $this->app->singleton(
            AuthManager::class,
            fn ($app) => new AuthManager($app->make(PluginRegistry::class)),
        );
        $this->app->alias(AuthManager::class, 'auth-kit');
    }

    public function boot(): void
    {
        $this->publishes([
            __DIR__ . '/../config/auth-kit.php' => $this->app->configPath('auth-kit.php'),
        ], 'auth-kit-config');

        $manager = $this->app->make(AuthManager::class);
        $registry = $manager->registry();

        // Register plugins declared in config (instances or class-strings).
        foreach ((array) config('auth-kit.plugins', []) as $plugin) {
            $instance = is_string($plugin) ? $this->app->make($plugin) : $plugin;

            if ($instance instanceof AuthPlugin && ! $registry->has($instance->id())) {
                $registry->register($instance);
            }
        }

        // Collect routes/migrations and boot AFTER every provider's boot() has
        // run, so plugins shipped as separate packages have self-registered by
        // then regardless of provider order.
        $this->app->booted(function () use ($manager, $registry): void {
            $this->loadPluginMigrations($registry);
            $this->loadPluginRoutes($registry);
            $manager->boot();
        });
    }

    private function loadPluginMigrations(PluginRegistry $registry): void
    {
        foreach ($registry->all() as $plugin) {
            if ($plugin instanceof HasSchema) {
                foreach ($plugin->migrationPaths() as $path) {
                    $this->loadMigrationsFrom($path);
                }
            }
        }
    }

    private function loadPluginRoutes(PluginRegistry $registry): void
    {
        $prefix = (string) config('auth-kit.prefix', 'auth-kit');
        $middleware = (array) config('auth-kit.middleware', ['api']);

        foreach ($registry->all() as $plugin) {
            if (! $plugin instanceof HasRoutes) {
                continue;
            }

            $routePaths = $plugin->routePaths();

            Route::prefix($prefix . '/' . $plugin->id())
                ->middleware($middleware)
                ->group(function () use ($routePaths): void {
                    foreach ($routePaths as $path) {
                        $this->loadRoutesFrom($path);
                    }
                });
        }
    }
}
