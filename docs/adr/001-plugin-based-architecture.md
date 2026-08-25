# ADR 001: Plugin-based architecture with separate-package plugins

- **Status:** Accepted
- **Date:** 2026-08-25
- **Deciders:** Gusman Widodo

## Context

We want a Laravel authentication framework in the spirit of `better-auth`
(TypeScript): a minimal core whose capabilities are extended through plugins.
The key question is *how plugins are packaged and how they integrate with the
core* within Laravel's service-provider and Composer conventions.

## Decision

1. **Small core, plugin contract.** The core ships only a plugin contract
   (`AuthPlugin`) plus three opt-in capability interfaces (`HasRoutes`,
   `HasSchema`, `HasHooks`), a `PluginRegistry`, an `AuthManager` (hook
   pipeline + lifecycle), and a service provider. It contains no concrete auth
   methods.

2. **Plugins are separate Composer packages in separate repos.** A plugin such
   as OTP lives in its own repository (`gusmanwidodo/auth-kit-otp`), depends on
   `gusmanwidodo/auth-kit`, and is published independently to Packagist. This
   mirrors better-auth's `better-auth` core + `better-auth/*` plugins model and
   lets third parties publish plugins without modifying the core.

3. **Self-registration via auto-discovery.** Each plugin package exposes its own
   service provider (auto-discovered by Laravel) that registers a plugin
   instance into the core `PluginRegistry`.

4. **Order-independent collection.** Provider `boot()` order is not guaranteed,
   so the core collects plugin routes/migrations inside an `app->booted()`
   callback — after every provider's `boot()` has run. This makes integration
   robust regardless of package load order.

5. **Namespaced routes.** Plugin routes are mounted under
   `/{prefix}/{plugin-id}/...` (default prefix `auth-kit`) to prevent
   collisions, matching better-auth's kebab-case, plugin-prefixed endpoint rule.

## Alternatives considered

- **Monorepo with plugins as subfolders.** Simpler to develop, but couples
  plugin releases to the core and blocks third-party plugins as first-class
  packages. Rejected — defeats the ecosystem goal.
- **Config-only plugin registration (no per-plugin provider).** Requires the app
  to manually list every plugin class. Kept as a supported fallback
  (`config/auth-kit.php`), but self-registration is the primary path.
- **Collecting routes in the provider `boot()` directly.** Fragile due to
  provider ordering. Rejected in favor of the `booted()` callback.

## Consequences

- **Positive:** Independent versioning/releasing of core and plugins; third
  parties can publish plugins; core stays tiny and stable; integration is
  order-independent and verified by cross-package integration tests.
- **Negative:** Local development against an unpublished core requires a Composer
  `path` repository (documented in the plugin README/CI). Two coordinated
  version constraints (`auth-kit-otp` requires `auth-kit ^0.1`).
- **Verification:** Core suite (5 tests) covers registry + hook pipeline; the
  OTP package suite (7 tests) proves a separate package self-registers, mounts
  routes, loads its migration, and drives the hook pipeline end-to-end.

## SOURCES

- better-auth plugins concept — https://better-auth.com/docs/concepts/plugins
- Laravel package development (auto-discovery) — https://laravel.com/docs/packages
