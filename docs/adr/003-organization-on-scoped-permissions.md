# ADR 003: Organization built on scoped permissions (consumer, not re-inventor)

- **Status:** Accepted
- **Date:** 2026-08-25
- **Deciders:** Gusman Widodo

## Context

We are adding an `auth-kit-organization` plugin (multi-tenant structure: an
organization has members, invitations, and an "active organization" per
session), mirroring better-auth's Organization plugin.

The central question: **where does organization access control live?**
better-auth's Organization plugin ships its own Access Control (owner/admin/
member roles, `hasPermission`). We already built `auth-kit-permissions` in ADR
002 with a **polymorphic scope** (`scope_type` + `scope_id`) specifically so a
tenant dimension could be layered on later without schema churn.

## Decision

1. **Organization is a CONSUMER of `auth-kit-permissions`, not a second RBAC
   system.** `auth-kit-organization` `require`s `gusmanwidodo/auth-kit-permissions`.
   It does not store role→permission mappings itself and does not implement its
   own permission check.

2. **Org roles are scoped dynamic roles.** When an organization is created, the
   plugin bootstraps three dynamic roles **scoped to that organization** via
   `RoleService::createRole(name, abilities, Scope::for('organization', $org->id))`:
   - `owner` — full control over `organization`, `member`, `invitation`.
   - `admin` — manage `member` + `invitation`; cannot delete the org or change
     the owner.
   - `member` — read only.
   The creator is assigned `owner` in that scope.

3. **Every org permission check flows through `PermissionManager::check(...,
   scope: Scope::for('organization', $org->id))`.** One source of truth (ADR
   002), one hook pipeline (`before:permission.check`), one memoized resolver.

4. **The `members` table stores membership, not authorization.** It records
   `(organization_id, subject)` and the human-facing role label for display, but
   the authoritative grant is the scoped role assignment in the permissions
   tables. This avoids the classic drift between a `member.role` string column
   and the real permission set.

5. **Active organization is session state.** The plugin persists the active org
   id in the session and exposes set/get; permission checks in a request use the
   active org's scope by default.

## Alternatives considered

- **Standalone org RBAC (own `member.role` string + own checks).** Lighter to
  build, but duplicates the access-control logic we already have, drifts from
  ADR 002, and means two places to reason about permissions. Rejected — the
  whole point of the polymorphic scope was to prevent this.
- **Optional integration (standalone, auto-sync if permissions present).** More
  moving parts and two code paths to test for no real benefit now that
  permissions is a stable dependency. Rejected for v0.1; can be revisited if a
  no-permissions install is ever demanded.
- **Teams in v0.1.** Deferred to v0.2. Teams are another scope
  (`scope_type = 'team'`) and drop into the same mechanism, so delaying them
  costs nothing architecturally.

## Consequences

- **Positive:** No duplicated RBAC; org authorization is exactly as fast and as
  consistent as the permissions plugin (static fast path + single-query dynamic
  + memoization). Teams/projects later reuse the identical scope mechanism.
  Proves the ADR-002 scope design end-to-end.
- **Negative:** `auth-kit-organization` has two upstream constraints
  (`auth-kit ^0.1`, `auth-kit-permissions ^0.1`). Creating an org performs a few
  writes (org row, member row, three role rows + assignment) — acceptable for a
  one-time setup operation, and roles are created once per org.
- **Verification:** The org package suite creates an organization, asserts the
  three scoped roles were bootstrapped, that the creator is `owner`, that an
  invited+accepted `member` can read but not delete, and that a permission
  granted in org A does **not** apply in org B — driving the real
  `PermissionManager` across the package boundary.

## SOURCES

- ADR 002 (hybrid access-control model) — ./002-hybrid-access-control-model.md
- better-auth Organization plugin — https://better-auth.com/docs/plugins/organization
- auth-kit-permissions (scope mechanism) — https://github.com/gusmanwidodo/auth-kit-permissions
