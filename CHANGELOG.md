# Changelog

All notable changes to `auth-kit` will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added
- Initial core: `AuthPlugin` contract with opt-in `HasRoutes`, `HasSchema`, `HasHooks`.
- `PluginRegistry` with duplicate-id protection.
- `AuthManager` with before/after hook pipeline and short-circuit support.
- `AuthKitServiceProvider` with auto-discovery, config publishing, and
  order-independent plugin route/migration collection via `booted()`.
- ADR 001 documenting the plugin-based architecture.
