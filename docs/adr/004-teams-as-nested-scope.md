# ADR 004: Teams as a nested scope (reuse, no access-control change)

- **Status:** Accepted
- **Date:** 2026-08-25
- **Deciders:** Gusman Widodo

## Context

`auth-kit-organization` v0.1 shipped organizations, members, invitations, and
active-org, with authorization delegated to `auth-kit-permissions` via
organization-scoped roles (ADR 003). ADR 003 explicitly deferred **teams** to
v0.2, predicting they would be "another scope, same mechanism, no schema change
to the access-control layer."

This ADR records the v0.2 teams design and tests that prediction.

## Decision

1. **Teams live in the organization plugin (v0.2.0), not a new package.** This
   mirrors better-auth, where teams are a mode of the organization plugin
   (`organization({ teams: { enabled: true } })`). Same repo, same Composer
   package, additive migration.

2. **A team is a `team`-scoped authorization context.** Team roles (`lead`,
   `member`) are created as dynamic roles scoped with
   `Scope::for('team', $team->id)` via the permissions plugin's `RoleService`.
   Every team permission check flows through `PermissionManager::check(...,
   scope: Scope::for('team', $team->id))`. **No change to `auth-kit-permissions`
   was required** — the polymorphic scope from ADR 002 already accepts a `team`
   type. This validates the ADR 002/003 design end-to-end.

3. **Teams are nested inside organizations.** `teams.organization_id` is a
   non-null FK to `auth_kit_organizations`. A subject may only be added to a team
   if they are already a member of that team's organization; the service enforces
   this and throws otherwise. Deleting an organization cascades to its teams.

4. **Team scope is independent of org scope, not hierarchical fallback.** A team
   permission check consults only the `team` scope. Organization-level power does
   NOT implicitly grant team-level power (and vice versa). This keeps the model
   simple, predictable, and consistent with how org scopes already behave; an
   app that wants "org owners can do anything in any team" composes that itself
   by also checking the org scope.

5. **Default team roles** (configurable):
   - `lead` — manage the team + its members (`team.update`, `team-member.*`).
   - `member` — read (`team.read`).

## Alternatives considered

- **Separate `auth-kit-teams` package.** More packaging overhead and a third
  upstream constraint for consumers, for a feature better-auth models as part of
  organizations. Rejected.
- **Teams as pure grouping (no RBAC).** Authorization would stay at org level and
  teams would just label membership. Cheaper, but loses per-team authorization —
  the main reason teams exist in multi-tenant apps. Rejected.
- **Hierarchical fallback (team check falls back to org).** Convenient but makes
  authorization non-local and harder to reason about ("why can this user do X in
  this team? — because of an org role three levels up"). Rejected in favor of
  explicit, composable checks. Can be added later as an opt-in helper without a
  schema change.
- **Flat teams (no org FK) / cross-org members.** Contradicts the nested tenancy
  model. Rejected.

## Consequences

- **Positive:** Teams reuse the entire access-control stack with zero changes to
  `auth-kit-permissions`; proves the scope abstraction. Projects/workspaces later
  are yet another `scope_type` with the same pattern.
- **Negative:** The org membership guard adds one existence check on team-member
  add. Authorization is intentionally non-hierarchical, so "org owner in every
  team" must be composed by the app (documented).
- **Verification:** The org package suite (v0.2) creates a team under an org,
  asserts the team-scoped roles were bootstrapped, that a team `lead` can manage
  the team but a team `member` cannot, that a non-org-member cannot be added to a
  team, and that team-scope permissions are isolated from org-scope and from
  other teams.

## SOURCES

- ADR 002 (hybrid access-control model) — ./002-hybrid-access-control-model.md
- ADR 003 (organization on scoped permissions) — ./003-organization-on-scoped-permissions.md
- better-auth Organization / Teams — https://better-auth.com/docs/plugins/organization
