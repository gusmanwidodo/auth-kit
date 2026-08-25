# Auth-Kit

A **plugin-based authentication framework for Laravel**, inspired by
[better-auth](https://better-auth.com). Small core, extend everything via
plugins that ship as **separate Composer packages**.

[![Tests](https://github.com/gusmanwidodo/auth-kit/actions/workflows/tests.yml/badge.svg)](https://github.com/gusmanwidodo/auth-kit/actions/workflows/tests.yml)
[![License: MIT](https://img.shields.io/badge/License-MIT-yellow.svg)](LICENSE)

## Philosophy

Like better-auth, the core does almost nothing on its own. It provides a plugin
contract and a registry; **features arrive as plugins**. A plugin can:

- add **HTTP endpoints** (mounted under `/{prefix}/{plugin-id}/...`)
- extend the **database schema** (ship its own migrations)
- hook into the **auth lifecycle** with `before` / `after` hooks

Plugins are distributed as independent packages (e.g.
[`gusmanwidodo/auth-kit-otp`](https://github.com/gusmanwidodo/auth-kit-otp)),
so the ecosystem grows without touching the core.

## Plugin registry

Official plugins maintained alongside the core. Each is a standalone Composer
package that only depends on `auth-kit`.

| Plugin | Package | Endpoints | Purpose |
|--------|---------|-----------|---------|
| [OTP](https://github.com/gusmanwidodo/auth-kit-otp) | `gusmanwidodo/auth-kit-otp` | `POST /auth-kit/otp/issue`, `POST /auth-kit/otp/verify` | One-time-password issue/verify with a hashed code store and expiry hook |
| [Magic Link](https://github.com/gusmanwidodo/auth-kit-magic-link) | `gusmanwidodo/auth-kit-magic-link` | `POST /auth-kit/magic-link/request`, `GET /auth-kit/magic-link/consume` | Passwordless login via single-use, time-limited links with expiry hook |
| [Permissions](https://github.com/gusmanwidodo/auth-kit-permissions) | `gusmanwidodo/auth-kit-permissions` | `POST /auth-kit/permissions/check` | Hybrid roles & permissions: static (zero-query) + dynamic (DB), polymorphic scoping, organization-ready. ~65× faster than spatie on the common check |
| [Organization](https://github.com/gusmanwidodo/auth-kit-organization) | `gusmanwidodo/auth-kit-organization` | `POST /auth-kit/organization/{create,invite,accept-invitation,set-active,check}` + `/teams/{create,add-member,check}` | Multi-tenant organizations, members, invitations, active-org, and nested teams. owner/admin/member and team lead/member as scoped roles via the permissions plugin |

> Building a plugin? Add a row here in the same format so it is discoverable
> from the core.

## Requirements

- PHP `^8.3`
- Laravel 12

## Installation

```bash
composer require gusmanwidodo/auth-kit
```

The service provider is auto-discovered. Publish the config if you want to tweak
the route prefix or middleware:

```bash
php artisan vendor:publish --tag=auth-kit-config
```

## How plugins work

A plugin is any class implementing `AuthPlugin`. Opt into extra capabilities by
also implementing the companion interfaces:

| Interface   | Grants the plugin the ability to… |
|-------------|-----------------------------------|
| `AuthPlugin` (required) | have a unique `id()` and a `boot()` lifecycle hook |
| `HasRoutes` | register route files, auto-prefixed with the plugin id |
| `HasSchema` | ship migrations, auto-loaded by the core |
| `HasHooks`  | run `before` / `after` logic on named lifecycle events |

### Minimal plugin

```php
use Gusmanwidodo\AuthKit\Contracts\AuthPlugin;

class HelloPlugin implements AuthPlugin
{
    public function id(): string { return 'hello'; }
    public function boot(): void {}
}
```

Register it either from a package service provider (recommended, see the OTP
plugin) or in `config/auth-kit.php`:

```php
'plugins' => [
    App\Auth\HelloPlugin::class,
],
```

### The hook pipeline

Plugins implementing `HasHooks` return a map of `event => callable`. The core
runs them in registration order and any hook can short-circuit the rest:

```php
public function beforeHooks(): array
{
    return [
        'otp.verify' => function (HookContext $ctx) {
            if ($ctx->get('expires_at') < now()->timestamp) {
                $ctx->set('valid', false)->stop();
            }
        },
    ];
}
```

Your code triggers the pipeline via the `AuthManager`:

```php
$ctx = app(AuthManager::class)->runBefore('otp.verify', [
    'expires_at' => $record->expires_at,
    'valid' => true,
]);

if ($ctx->get('valid') === false) { /* reject */ }
```

## Architecture

See [`docs/adr/001-plugin-based-architecture.md`](docs/adr/001-plugin-based-architecture.md)
for the design decision behind the plugin model, and
[`docs/adr/002-hybrid-access-control-model.md`](docs/adr/002-hybrid-access-control-model.md)
for the access-control model used by the permissions plugin, and
[`docs/adr/003-organization-on-scoped-permissions.md`](docs/adr/003-organization-on-scoped-permissions.md)
for how the organization plugin builds on scoped permissions, and
[`docs/adr/004-teams-as-nested-scope.md`](docs/adr/004-teams-as-nested-scope.md)
for teams as a nested authorization scope.

## Testing

```bash
composer install
composer test
```

## License

MIT © Gusman Widodo. See [LICENSE](LICENSE).
