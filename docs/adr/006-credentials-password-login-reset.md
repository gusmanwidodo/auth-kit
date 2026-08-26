# ADR 006: Credentials plugin — flexible-identifier password login + reset

- **Status:** Accepted
- **Date:** 2026-08-26
- **Deciders:** Gusman Widodo

## Context

The Auth-Kit ecosystem shipped OTP, magic-link, OAuth (Socialite), RBAC, and
multi-tenant organizations/teams — but **not** the most fundamental auth method:
register/login with an identifier + password, and forgot/reset password. This is
a core capability in better-auth (`emailAndPassword`) and every real app needs
it. This ADR records the `auth-kit-credentials` plugin design.

## Decision

1. **New plugin `auth-kit-credentials`.** Same model as the other plugins:
   separate Composer package, `HasSchema` + `HasRoutes`, self-registering, hooks
   on the core pipeline.

2. **Flexible single `identifier` column.** Rather than separate email / phone /
   username columns, a credential stores one `identifier` string plus an
   `identifier_type` (`email` | `phone` | `username`, extensible via config).
   Login accepts any configured type. This is the most generic model and lets an
   app support "log in with email OR username" without schema changes. The pair
   `(identifier_type, identifier)` is unique.

3. **Credentials live in the plugin's own table.** `auth_kit_credentials` stores
   `(identifier_type, identifier, password_hash, subject_type, subject_id,
   verified_at)`. Passwords are hashed with Laravel's `Hash` (bcrypt/argon per
   the app's `hashing` config). The plugin is self-contained and does not assume
   a `users.password` column, consistent with the rest of the ecosystem.

4. **Subject is polymorphic and provisioned by the plugin on register.** Unlike
   the social plugin (where the app owns provisioning), `register()` creates the
   credential and, if the app wants, links it to a subject the app supplies via
   the `before:credentials.register` hook. If no subject is supplied, the
   credential itself is the identity (subject_type/id nullable) — the app can
   attach a user later. Login returns the linked subject.

5. **Password reset uses a hashed, single-use, expiring token.** A separate
   `auth_kit_password_resets` table stores only the **hash** of the reset token
   (never the plaintext), with a unix `expires_at` and a `consumed_at` marker.
   `forgot()` issues a token (the app delivers it by email/SMS — the plugin never
   sends it); `reset()` validates hash + expiry + unused, then updates the
   password hash and burns the token. This mirrors the OTP/magic-link token
   design already proven in the ecosystem.

6. **Timing-safe login.** Login always runs a hash comparison (against a dummy
   hash when the identifier is unknown) so response timing does not leak whether
   an identifier exists. Hooks: `before:credentials.login` (veto, e.g. locked
   accounts), `after:credentials.login`, plus `before/after:credentials.register`
   and `after:credentials.reset`.

## Alternatives considered

- **Separate email/phone/username columns.** Explicit but rigid; adding an
  identifier type means a migration. The flexible single-column model avoids
  that. Rejected.
- **Store password on the app's users table (Laravel-standard).** Couples the
  plugin to the app's schema and breaks the self-contained pattern every other
  Auth-Kit plugin follows. Rejected.
- **Plaintext or reversibly-stored reset tokens.** A leaked DB would expose live
  reset tokens. Hashing the token (as with OTP codes) is the safe default.
  Rejected.
- **Plugin sends the reset email/SMS.** Delivery is the app's concern (mail/SMS
  config, templates, localization). The plugin returns the token in tests and
  exposes it for the app to deliver. Rejected baking delivery in.

## Consequences

- **Positive:** Fills the core gap; self-contained; flexible identifier; secure
  reset flow; timing-safe login; composes with the rest (a registered subject can
  then get roles via permissions, join organizations, link social accounts).
- **Negative:** The app must deliver the reset token itself (documented). Two
  tables added. Identifier normalization (lowercasing email, phone formatting) is
  left to the app/config to avoid wrong assumptions.
- **Verification:** The package suite asserts: register creates a hashed
  credential; login succeeds with the right password and fails with the wrong
  one; unknown identifier fails without leaking timing (dummy hash path
  exercised); forgot issues a token and reset changes the password; expired and
  reused reset tokens are rejected; and a `before:credentials.login` hook can
  veto.

## SOURCES

- better-auth email & password — https://better-auth.com/docs/authentication/email-password
- Laravel hashing — https://laravel.com/docs/12.x/hashing
- ADR 001 (plugin architecture) — ./001-plugin-based-architecture.md
- OTP token design — https://github.com/gusmanwidodo/auth-kit-otp
