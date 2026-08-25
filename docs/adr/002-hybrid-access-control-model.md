# ADR 002: Hybrid access-control model (static + dynamic, scope-aware)

- **Status:** Accepted
- **Date:** 2026-08-25
- **Deciders:** Gusman Widodo

## Context

We need a roles-and-permissions plugin for Auth-Kit that is **more performant
than `spatie/laravel-permission`** and **forward-compatible with a future
`organization` plugin** (mirroring better-auth's Organization + Access Control
design).

Two problems with a naive DB-only model (the shape `spatie/laravel-permission`
uses):

1. **Every permission check can hit the database.** In hot code paths (policies,
   middleware, Blade directives) this becomes N+1 unless the app carefully eager
   loads and caches. spatie mitigates with a global permission cache, but the
   cache is process/tenant-global and must be invalidated carefully.
2. **Roles/permissions are global by default.** Multi-tenant scoping ("teams")
   is bolted on via an extra `team_id` column threaded through every table.

better-auth solves both differently: roles can be **static** — defined in code as
a statement (`resource => actions`) — so the common check is a synchronous,
**zero-query** set-membership test; and **dynamic** roles are stored per
organization in the database only when runtime-defined roles are needed.

## Decision

1. **Hybrid model.** Two cooperating layers behind one `Gate`-friendly API:
   - **Static access control** (in-memory): an `AccessControl` built from a
     `statement` (`['post' => ['create','update','delete']]`). Roles are subsets
     of that statement. A permission check against a static role is pure
     in-memory set logic — **zero queries**. This is the fast path and the
     primary performance advantage over spatie.
   - **Dynamic access control** (database): roles/permissions/assignments stored
     in tables for runtime-defined roles. Resolved in **one query per (subject,
     scope)** and **memoized for the rest of the request**.

2. **Polymorphic scope.** Every assignment and every check carries a nullable
   polymorphic scope (`scope_type` + `scope_id`). `null` scope = global. This is
   the bridge to the future `organization` plugin: it assigns roles with
   `scope_type = 'organization'`, `scope_id = <org id>` and checks within that
   scope — **no schema change required** when the organization plugin ships. The
   same mechanism serves team/project scopes.

3. **Unified check API.** `AuthKitPermissions::check($subject, $ability,
   $scope)` consults static roles first (zero-query), then dynamic assignments
   (single memoized query) — so callers never care which layer answered.

4. **Runs the core hook pipeline.** Checks fire `before:permission.check`, so
   other plugins (audit, rate-limit, organization) can observe or veto.

## Alternatives considered

- **DB-only, optimized (spatie shape + memoization).** Simpler mental model, but
  even with per-request memoization the *first* check in a request always pays a
  query, and static/compile-time roles (the overwhelmingly common case) can't be
  answered with zero queries. Rejected as the primary model; the optimizations
  (single-query resolve, memoization) are kept for the dynamic layer.
- **Static-only (bitmask, zero DB).** Fastest, but cannot express runtime-created
  roles per organization — a hard requirement for the organization plugin.
  Rejected as the sole model; kept as the fast path within the hybrid.
- **Non-polymorphic `scope_id` (nullable, org-only).** Simpler, but locks scoping
  to organizations. Polymorphic (`scope_type` + `scope_id`) costs one extra
  column and unlocks team/project scopes for free. Chosen.

## Consequences

- **Positive:** Common checks are zero-query (static roles); dynamic checks are
  single-query + memoized; scoping is organization-ready with no future schema
  change; one API regardless of layer.
- **Negative:** Two layers to understand vs spatie's one. Polymorphic scope adds
  a `scope_type` column that is `null` for global (small storage cost).
- **Verification:** The `auth-kit-permissions` package suite asserts (a)
  correctness of static + dynamic + scoped checks, (b) **query counts** via the
  DB query log (static check = 0 queries; dynamic resolve = 1 query; repeated
  checks in a request = 0 additional queries), and (c) a **real micro-benchmark**
  comparing checks/second against `spatie/laravel-permission`, with results
  published in the package README.

## SOURCES

- better-auth Organization / Access Control — https://better-auth.com/docs/plugins/organization
- better-auth access primitives (`createAccessControl`) — https://better-auth.com/docs/plugins/organization#access-control
- spatie/laravel-permission — https://github.com/spatie/laravel-permission
- ADR 001 (plugin architecture) — ./001-plugin-based-architecture.md
