# Per-tenant SMTP / Email Settings + Resilient Notifications

**Date:** 2026-06-18
**Branch context:** `feature/branch-scoped-tournaments` (will land on its own feature branch)
**Status:** Approved design — ready for implementation plan

## Problem

On production (`getcourtmaster.com`) the shared SMTP server rejects every send with
`530 5.7.0 Authentication required`. Tenant notifications are sent **synchronously inside
the cancel DB transaction**, so the mail exception rolls back the transaction and the cancel
returns a **500**. The owner/staff `notify()` loop in
`BookingService::notifyOwnerStaff()` has no try/catch (unlike the CC path right below it).

Beyond the immediate crash, this is a **multi-tenant SaaS**: tenants want to control their
own email — turn it on/off and (optionally) send through their own SMTP server so mail comes
from their own domain. Today mail is a single global `.env` configuration shared by everyone.

## Goals

1. **A mail failure must never break a core operation** (cancel, booking, refund, etc.).
2. Each tenant can **enable/disable** business email notifications.
3. Each tenant can **optionally supply their own SMTP credentials**; if they don't, business
   email **falls back to the platform `.env` mailer** so everyone keeps receiving email
   (hybrid model — branded sender when configured, platform mailer otherwise).
4. A tenant can **test** their SMTP credentials before relying on them.
5. An optional per-tenant **"require SMTP"** strict toggle that surfaces a visible warning
   when mail could not be delivered — without ever aborting the operation.

## Non-goals (YAGNI)

- Per-tenant choice of non-SMTP transports (SES/Postmark/Mailgun API). SMTP only.
- Queue-based notification delivery (this box runs no queue worker; see memory
  `auto-stop-after-grace`). Sends stay synchronous but are made non-fatal.
- Changing platform/auth email (OTP login, password reset, super-admin). Those keep using
  the platform `.env` mailer — see "Boundary" below.
- Removing the shared `.env` mailer. It is retained as the platform fallback for tenants who
  have not configured their own SMTP (hybrid model).
- Per-user channel preferences — already handled by `HonorsUserChannelPreferences`.

## Boundary: tenant business mail vs platform/auth mail

| Category | Examples | Mailer used |
|---|---|---|
| **Tenant business notifications** | booking created/cancelled/approved, low-stock, membership expiry, the `notification_email` CC | Tenant's own SMTP if configured; else **platform `.env` fallback**; **discarded** only if the tenant disabled email |
| **Platform / auth mail** | OTP login (`OtpLoginController`), password reset, super-admin | Platform `.env` mailer, always |

Rationale (hybrid): tenants who configure their own SMTP get a branded sender and isolation;
tenants who don't still receive email through the platform mailer, so onboarding and login OTP
never break.

## Architecture

### 1. `TenantMailManager` service (mailer resolution)

A single service owns the decision of *which mailer a tenant's business notifications use*.

```
resolveMailerFor(Tenant $tenant): string   // returns a mailer name to use for this request
```

Decision table:

| Condition | Result |
|---|---|
| `settings.notifications.email_enabled` is `false` | `array` (discard — no send, no throw) |
| tenant has complete SMTP credentials | a per-request `tenant_smtp` mailer built from them |
| otherwise (no SMTP saved) | **platform `.env` mailer** (hybrid fallback) |

- The `tenant_smtp` mailer is registered at runtime by overriding
  `config(['mail.mailers.tenant_smtp' => [...]])` from the decrypted credentials, including a
  per-tenant `from` address/name.
- The platform fallback is the snapshotted original default mailer (the `.env` `MAIL_MAILER`,
  normally `smtp`). Email keeps flowing for tenants who never configure their own SMTP.
- "Discard" (only when a tenant explicitly disables email) uses Laravel's existing `array`
  transport (`config/mail.php` already defines it): the mail channel "sends" successfully to
  nowhere, so **database / webPush notification channels are unaffected** and nothing throws.
- The service exposes `wouldDeliver(Tenant $tenant): bool` — true unless the tenant disabled
  email; callers use it for the strict-mode warning. Because of the platform fallback, the only
  case where no email is even attempted is `email_enabled = false`.

### 2. `SetTenantMailer` middleware

Registered on the authenticated web stack. At request start, resolves the current tenant
(via the existing tenant context used elsewhere) and sets that tenant's business mailer as the
**default mailer for the request** via `config(['mail.default' => ...])`, after snapshotting
the platform default.

- Auth routes that send platform mail (login/OTP/password reset) are **excluded** so they keep
  the platform mailer. Implementation: the middleware only applies the override for
  authenticated tenant users; OTP/reset happen pre-auth, so they are naturally excluded.
- Jobs/commands (reminders, renewals) run without HTTP context: they call
  `TenantMailManager` explicitly per tenant before dispatching that tenant's notifications,
  and restore the default after. (See "Jobs" task.)

### 3. After-commit dispatch + try/catch (the 500 fix)

- Move tenant notification dispatch so it runs **after** the surrounding DB transaction
  commits (`DB::afterCommit(...)` / dispatch after the `transaction()` closure returns), so a
  mail failure can never roll back saved data.
- Wrap every synchronous owner/staff `notify()` send in try/catch that logs a warning,
  mirroring the existing CC `try/catch` at `BookingService.php:439-443`.
- Audit and apply the same guard to the other synchronous mail paths:
  `SendBookingCreatedNotification`, `SendBookingConfirmation` listeners; `SendBookingReminder`,
  `ProcessMembershipRenewals`, `CheckLowStock`, `CheckOvertimeTimers`, `GenerateReportExport`
  jobs.

### 4. Strict "require SMTP" warning (non-fatal)

- `settings.mail.require_smtp` (default **false**). Meaning: "require my **own** SMTP" — the
  tenant wants mail to go through their configured server, not the platform fallback.
- When **true** and the tenant has no usable own SMTP (so business mail would either fall back
  to the platform mailer or be discarded), flash a visible warning to the staff user
  (e.g. *"Saved — but email is going through the platform mailer because your SMTP isn't
  configured."*) and show a persistent banner on the Email settings tab.
- When **true** and an actual send through the tenant's own SMTP fails, the caught error is
  surfaced as a warning flash too.
- It **never** aborts or rolls back. Best-effort always wins on data integrity.
- When **false**, fallback/failures are logged only (the platform fallback still delivers).

## Data model

New encrypted column on `tenants`, mirroring `payment_credentials`:

- Migration: add `mail_credentials` JSON/text column (nullable).
- `Tenant` model: cast `'mail_credentials' => 'encrypted:array'`, add to `$hidden`, add to
  `$fillable` is **not** needed (set explicitly like `payment_credentials`).
- Shape:
  ```php
  [
    'host' => string, 'port' => int, 'encryption' => 'tls'|'ssl'|null,
    'username' => string, 'password' => string,   // password stored encrypted within the blob
    'from_address' => string, 'from_name' => string,
  ]
  ```

Settings JSON (`tenants.settings`, existing `array` cast):

- `settings.notifications.email_enabled` (bool, default treated as **true** when absent, to
  preserve current behavior).
- `settings.mail.require_smtp` (bool, default **false**).

Helper methods on `Tenant`:

- `mailEnabled(): bool` — `data_get(settings,'notifications.email_enabled', true)`.
- `requireSmtp(): bool` — `data_get(settings,'mail.require_smtp', false)`.
- `smtpCredentials(): ?array` — decrypted `mail_credentials` if complete, else null.

## UI — Settings → new "Email" tab

Mirrors the existing **Gateways** tab pattern (`SettingsController::updateGateways`,
`admin/settings/index.blade.php`).

- **Master toggle:** "Send email notifications" (`email_enabled`). Staff-editable.
- **Strict toggle:** "Require my own SMTP (warn if mail uses the platform mailer)"
  (`require_smtp`). Staff-editable.
- **SMTP credentials** (host, port, encryption, username, password, from address, from name):
  - **Owner-only** (`isBusinessOwner()` / `isSuperAdmin()`), exactly like gateway credentials
    (`SettingsController.php:188-191`).
  - Password field is **masked**; submitting it blank preserves the stored value
    (empty-keeps-existing pattern from `updateGateways`).
- **"Send test email" button** (owner-only): POSTs to a `testMail` action that builds the
  tenant's resolved mailer and sends a simple test message to the owner's address, returning
  success or the transport error string (so the 530-auth case is visible immediately).
- Banner when `require_smtp` is on but the tenant has no usable own SMTP (mail is falling back
  to the platform mailer, or is disabled).

## Controller actions (`SettingsController`)

- `updateNotifications` — extend to also persist `email_enabled` (keeps the existing
  `notify_*` + `notification_email` keys; do not clobber them).
- `updateEmail` (new) — owner-gated; validates and saves SMTP credentials with the
  empty-keeps-existing rule; saves `require_smtp`.
- `testMail` (new) — owner-gated; sends a test through `TenantMailManager`, catches throwables,
  flashes the result.

Routes added under the existing admin settings group.

## Error handling summary

- Inside transactions: nothing mail-related throws upward (after-commit + try/catch).
- Discarded sends: no throw, logged at debug.
- Test send: throwable caught and shown to the user verbatim (trimmed).
- Strict mode: warning surfaced, never fatal.

## Testing

- **TenantMailManager unit:** disabled → `array`; no creds → platform `.env` mailer (fallback);
  complete creds → `tenant_smtp` with the decrypted host/port/from; `wouldDeliver()` truth table.
- **Resilience:** cancelling a booking while the mailer throws still commits the cancel and
  returns success (regression test for the live 500). Use `Mail::fake()` /
  forced-throw transport.
- **Settings:** owner can save SMTP creds (encrypted at rest, blank password preserved);
  staff is 403 on the SMTP fields but can toggle `email_enabled`.
- **Boundary:** OTP/auth mail still uses the platform mailer regardless of tenant config.
- NOTE: full `php artisan test` is known-broken on sqlite (memory
  `test-suite-sqlite-broken`); verify new tests in isolation / via tinker where the suite
  can't boot.

## Rollout / behavior preservation

- `email_enabled` absent → treated as **on**, so existing tenants keep getting in-app
  notifications and email exactly as before.
- Hybrid model fully preserves old behavior: a tenant with **no** SMTP saved still receives
  business email through the platform `.env` mailer. The only behavior *additions* are: a
  tenant can now (a) point mail at their own SMTP, or (b) explicitly turn email off.
- Platform/auth mail unchanged.

## Open follow-ups (out of scope, noted only)

- The platform `.env` SMTP itself is still misconfigured (530). Because the hybrid model relies
  on it as the fallback, fixing those credentials (valid `MAIL_USERNAME`/`MAIL_PASSWORD`) is
  required for unconfigured tenants — and for auth/OTP — to actually receive mail. This is an
  **ops fix**, not code; the code changes here stop it from causing a 500 either way.
