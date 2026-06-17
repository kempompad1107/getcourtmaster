# Pickleball Court Management SaaS — Master Specification

> Authoritative spec for the multi-tenant Pickleball Court Management SaaS built on
> Laravel 12 (spec calls for "Laravel 11+"), MySQL, TailwindCSS v4, Alpine.js,
> Livewire 4, Sanctum, Spatie Permission/ActivityLog/MediaLibrary, Pusher,
> Predis, DomPDF, Maatwebsite/Excel.
>
> **Status legend**
> - ✅ Done — implemented and wired
> - 🟡 Partial — scaffolded but missing pieces
> - ❌ Missing — not yet implemented
>
> Last audit: 2026-05-22.

---

## 1. Core SaaS Architecture — ✅ Done

**Implemented**
- `tenants`, `tenant_settings`, `tenant_subscriptions` tables and models
- `SetTenantContext` + `EnsureTenantIsActive` middleware
- spatie/laravel-permission with role gating (`role:super_admin`, …)
- User `user_type` discriminator + `isSuperAdmin/…` helpers
- Tenant impersonation, suspend, activate routes
- Per-tenant scopes on most models (`scopeOfTenant`)
- Customer portal (Dashboard / Bookings / Memberships / Wallet / Profile)

**Notes** — Single-DB-with-tenant_id isolation (not Stancl/Tenancy). Add a
permission seeder for staff sub-roles (cashier / booking manager / etc.) if you
introduce role granularity beyond the four primary roles.

---

## 2. Authentication System — ✅ Done

**Implemented**
- Email/password login + register
- Forgot password / reset
- Email verification (`MustVerifyEmail`)
- Socialite (Google + Facebook)
- TOTP-based 2FA: setup, confirm, disable, mid-login challenge, recovery codes
- OTP login (passwordless) flow + queued `OtpCodeNotification`
- Device/session management UI (`/account/devices`) with revoke + revoke-others
- Rate limiters on login, otp-request, password-reset

---

## 3. Subscription Billing (SaaS) — ✅ Done

**Implemented**
- `subscription_plans`, `tenant_subscriptions`, `subscription_invoices` models
- `BillingService` (renewals, retries, suspension on overdue)
- `ProcessBillingRetries` queue job
- `RequirePlanFeature` middleware (`plan:feature_name`) for per-route gating
- Invoice PDF download (`subscription-invoices/{invoice}/pdf`)
- Stripe + PayMongo gateways wired (recurring via stored payment methods)

**Notes** — Apply `plan:` middleware to premium-only routes (analytics, advanced
reports, multi-branch) per business policy.

---

## 4. Pickleball Court Management — ✅ Done

`Court`, `CourtPricingRule`, `Branch` models; status board; media via Spatie;
`PricingService` for peak / non-peak / holiday math; real-time status updates
via broadcast events.

---

## 5. Booking & Reservation System — ✅ Done

**Implemented**
- Online + walk-in flows
- Availability + conflict prevention
- Calendar JSON endpoint
- Confirm / cancel / reschedule
- Waitlist (`WaitlistEntry`)
- QR confirmation (`QrCodeService`)
- Cancellation refund rules driven by tenant settings (`refund.full_window_hours`,
  `refund.partial_window_hours`, `refund.partial_percent`) → `WalletService` credit

---

## 6. Court Rental Timer System — ✅ Done

**Implemented**
- `BookingTimer` model + Start/Pause/Resume/Extend/Stop endpoints
- `CheckOvertimeTimers` queued job + auto-charge in `stopTimer`
- `TimerUpdated`, `CourtStatusChanged` events implement `ShouldBroadcast`
- Pusher/Reverb broadcasting configured
- Livewire `BookingTimerPanel` + `CourtStatusBoard` components subscribed to channels

---

## 7. POS / Sales System — ✅ Done

**Implemented**
- `Product`, `ProductCategory`, `PosOrder`, `PosOrderItem`, `PosPayment` models
- `PosService` + controller (index/store/receipt/void/history)
- Standard receipt + thermal receipt routes
- Barcode lookup endpoint (`/admin/pos/barcode`)
- Split / partial payments (`/admin/pos/orders/{order}/payment`)
- Cash drawer: `CashDrawerLog` model, `CashDrawerService`, drawer routes
- Discounts, promos, tax handled by `PosService`

---

## 8. Payment Processing — ✅ Done

**Implemented**
- `Payment` model (polymorphic `payable`)
- `PaymentService` + `GatewayManager` with PayMongo (GCash/Maya/Card) and Stripe drivers
- Webhook signature verification (HMAC) per gateway
- Offline payment flow with proof upload + verify (`payments/{payment}/proof`, `/verify`)
- Refunds → wallet credit
- Official Receipt PDF (`payments/{payment}/receipt-pdf`)
- Statuses: pending / paid / failed / refunded / partial

---

## 9. Membership Management — ✅ Done

**Implemented**
- `MembershipPlan`, `Membership`, `MembershipTransaction`
- `MembershipService` with subscribe / renew / freeze / cancel / useCredit
- Court credit consumption on booking create (`use_credit` flag → free booking)
- `ProcessMembershipRenewals` job
- `MembershipExpiryNotification` + `MembershipRenewedNotification` (queued)
- Referral reward on first paying booking (`issueReferralRewardIfFirstBooking` → `WalletService::credit`)

---

## 10. Customer Management (CRM) — ✅ Done

**Implemented**
- Admin `CustomerController` (search, credit, notes)
- `CustomerNote` model
- Customer-facing portal (Dashboard / Bookings / Memberships / Wallet / Profile)
- `AnalyticsService::topCustomersByLtv`, `churnRate`, `retentionRate`

---

## 11. Inventory Management — ✅ Done

**Implemented**
- `Product`, `ProductCategory`, `InventoryMovement`
- `ProductController` with movements + `adjustStock`
- `Supplier`, `PurchaseOrder`, `PurchaseOrderItem` models + admin CRUD + receive
- `CheckLowStock` queued job → `LowStockNotification`

---

## 12. Reports & Analytics Dashboard — ✅ Done

**Implemented**
- `ReportController` (revenue / occupancy / financial / customers / export / pdf)
- `ReportService` + `GenerateReportExport` queued job
- `ReportReadyNotification`
- `AnalyticsService::revenueByDay`, `bookingsByDay`, `bookingsByHour`, `topCustomersByLtv`, `churnRate`, `retentionRate`
- `AnalyticsController@overview` JSON endpoint for dashboard charts
- DomPDF + Maatwebsite/Excel for export

---

## 13. Notifications System — ✅ Done

**Implemented**
- Notification classes for booking / membership / report / low-stock / OTP
- All notifications queued (`ShouldQueue` where appropriate)
- Channels:
  - `mail` — Laravel default
  - `database` — in-app notifications + dropdown UI (`/notifications/dropdown`)
  - `sms` — SemaphoreSmsChannel (Philippines provider via `SEMAPHORE_API_KEY`)
  - `webpush` — `WebPushChannel` with VAPID (set `VAPID_PUBLIC_KEY` / `VAPID_PRIVATE_KEY`; needs `minishlink/web-push` composer dep for actual delivery)
- `HonorsUserChannelPreferences` trait — every notification consults
  `users.notification_preferences` JSON (email / sms / push / in_app keys)
- Push subscription endpoints: `/notifications/push/subscribe|unsubscribe`
- Service worker `push` + `notificationclick` handlers in `public/sw.js`

**Setup note** — to enable real web push, run
`composer require minishlink/web-push` and set VAPID env vars. Without those, the
channel logs and skips delivery (no errors).

---

## 14. Promotions Engine — ✅ Done

**Implemented**
- `Promotion`, `PromotionUsage` models
- `PromotionController` + validate endpoint
- `PromotionRuleEngine` — supports percentage / fixed / bundle, hourly window
  (`applicable_from_time`/`to_time`), day-of-week (`applicable_days`), court
  whitelist (`applicable_courts`), holiday list (tenant `settings.holidays`),
  min spend, max discount
- Referral reward auto-issued via `BookingService::issueReferralRewardIfFirstBooking`

---

## 15. Staff Attendance & Shift Logs — ✅ Done

**Implemented**
- `Shift`, `StaffProfile` models
- `StaffController` with shifts list, clock-in, clock-out
- `TrackUserSession` middleware records session per request
- `ActivityLogController` for per-staff audit view (via spatie/laravel-activitylog)

---

## 16. Audit Logs & Security — ✅ Done

**Implemented**
- spatie/laravel-activitylog wired
- `LogsActivity` trait on: Booking, Court, Payment, Membership, Promotion,
  PosOrder, PurchaseOrder, **User** (added 2026-05-22)
- `CausesActivity` on User
- Admin audit log UI (`/admin/audit`)
- Rate limiters on login / otp / password-reset / payment-webhook / booking-create
- Sanctum API tokens
- Laravel default CSRF/XSS
- 2FA secret + recovery codes encrypted at rest (`Crypt::encryptString`)

---

## 17. API-Ready Architecture — ✅ Done

REST API under `routes/api.php` with V1 controllers: Auth, Booking, Court,
Membership, Payment, PaymentWebhook, Wallet, Notification. Laravel Sanctum
auth. Throttled at 60/min by default.

**Optional polish** — generate API resources / transformers + OpenAPI spec.

---

## 18. Smart Display Mode — ✅ Done

`DisplayController@index/data`; `/display` (public) + `/admin/display`
(tenant-scoped). `CourtStatusChanged` + `TimerUpdated` events broadcast on
Pusher/Reverb channels. Polling fallback retained for clients without WebSocket.

---

## 19. Mobile Responsive PWA — ✅ Done

`public/manifest.json`, `public/sw.js`, `public/offline.html`. Service worker
handles offline cache, background-sync of pending bookings, and push
notifications.

---

## 20. Technical Standards — ✅ Done

**Implemented**
- Service layer: 12+ services in [app/Services/](app/Services/)
- Repository pattern: `app/Repositories/Contracts/` + `Eloquent/` for Booking,
  Court, Customer, Membership, Payment
- Events / Listeners / queue jobs
- Soft deletes on Booking + Tenant
- Policies (Booking / Court / Membership) + spatie/permission gates
- Tests: 7 feature tests + 2 unit tests (Booking, Membership, PaymentWebhook,
  Pos, PromotionRuleEngine, RefundRule, Timer, Api, Totp)
- Migrations, seeders, logging, error handling

**Polish opportunities** — broaden test coverage; document caching strategy;
codify a production checklist into [DEPLOYMENT.md](DEPLOYMENT.md).

---

## 21. UI/UX — 🟡 Partial

**Implemented**
- TailwindCSS v4 + Alpine
- Dark mode with localStorage
- Reusable Blade components in `resources/views/components/`
- Polished admin + customer portals

**Open polish** — Live dashboards (Chart.js wiring), animations, additional
empty-state and skeleton-loader components.

---

## 22. Deliverables

All deliverables in scope are present: schema, migrations, models, controllers,
policies, services, repositories, routes, UI, APIs, tests, queue workers,
DEPLOYMENT.md.

---

## Cross-cutting Remaining

1. UI polish: chart wiring on dashboards, additional skeleton states (§21)
2. Broaden test coverage (§20)
3. Run `composer require minishlink/web-push` + set VAPID env vars to activate
   real web push delivery (§13) — channel skeleton already routes to it
4. Apply `plan:feature_name` middleware to premium-only routes per business
   policy (§3)
5. Optional: API resource transformers + OpenAPI doc (§17)
