# ADR 005: Social login as a thin wrapper over Laravel Socialite

- **Status:** Accepted
- **Date:** 2026-08-25
- **Deciders:** Gusman Widodo

## Context

We want social/OAuth login (Google, GitHub, etc.) in the Auth-Kit ecosystem,
compatible with [Laravel Socialite](https://github.com/laravel/socialite) — the
de-facto Laravel OAuth client.

Socialite deliberately does **one** thing: it drives the OAuth handshake and
returns a normalized `Socialite\Contracts\User` (id, nickname, name, email,
avatar, token, refreshToken). It does **not** persist anything, does not link an
OAuth identity to a local user, and does not support multiple providers per
user. Those are exactly the concerns an application must re-implement every time.

## Decision

1. **Build `auth-kit-social` as a thin wrapper over Socialite, not a
   reimplementation.** The package `require`s `laravel/socialite` and delegates
   the entire OAuth handshake to it (`Socialite::driver($p)->redirect()` /
   `->user()`). We never touch OAuth protocol details. This keeps us compatible
   with every current and future Socialite driver (including community
   `socialiteproviders.com` adapters) for free.

2. **Persist social identities in our own table.** `auth_kit_social_identities`
   stores `(provider, provider_id) -> (subject_type, subject_id)` plus the OAuth
   `token` / `refresh_token` **encrypted at rest** (Laravel `encrypted` cast).
   A unique `(provider, provider_id)` prevents duplicate links; a subject may
   hold **multiple** identities (one per provider), enabling "link another
   account".

3. **The plugin owns linking, not user creation.** `SocialManager::authenticate()`
   takes a provider name + the Socialite user and:
   - if an identity exists → returns the linked subject (login),
   - if not → runs the `before:social.login` hook (so the app/other plugins can
     veto or supply a subject) and creates the identity against the subject the
     app resolves.
   User provisioning stays in the app (or a hook), matching Socialite's own
   "authenticate and storage" guidance — we don't presume the app's user model
   shape.

4. **Full plugin surface + core hooks.** `HasSchema` (identities migration),
   `HasRoutes` (`/auth-kit/social/{provider}/redirect|callback`, plus
   `link`/`unlink`), and the core hook pipeline events `before:social.login` /
   `after:social.login` so audit, permissions, or organization plugins can react.

5. **Testing uses `Socialite::fake()`.** Per Socialite's documented testing
   story, the suite fakes the driver and asserts our linking/storage/hook
   behavior without any real OAuth round-trip.

## Alternatives considered

- **Reimplement OAuth ourselves.** Pointless — Socialite is mature, maintained,
  and has a huge driver ecosystem. Rejected.
- **Stateless (no storage), just normalize + hook.** Simpler, but every consumer
  re-solves identity linking and multi-provider storage. The user explicitly
  wants the plugin to own linking. Rejected.
- **Socialite optional (suggest, not require).** Two code paths and a degraded
  mode for no real benefit; social login without an OAuth client is meaningless.
  Rejected — Socialite is a hard dependency.
- **Auto-provision users by matching email.** Convenient but risky (email
  takeover if a provider doesn't verify emails). Left to the app via the
  `before:social.login` hook instead of baked in. Rejected as a default.

## Consequences

- **Positive:** Compatible with all Socialite drivers; encrypted token storage;
  multi-provider linking; consistent with the Auth-Kit plugin model and hook
  pipeline. No OAuth code to maintain.
- **Negative:** Hard dependency on `laravel/socialite`. User provisioning is not
  automatic — the app must resolve/create the subject (documented, via hook).
- **Verification:** The package suite uses `Socialite::fake()` to assert: a new
  provider login creates an identity; a returning login resolves the existing
  subject; a second provider links as an additional identity; unlink removes it;
  tokens are stored encrypted (ciphertext != plaintext in the DB); and a
  `before:social.login` hook can veto.

## SOURCES

- Laravel Socialite docs — https://laravel.com/docs/12.x/socialite
- Socialite repo — https://github.com/laravel/socialite
- ADR 001 (plugin architecture) — ./001-plugin-based-architecture.md
