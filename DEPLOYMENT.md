# CourtMaster — Production Deployment Guide

> Last updated: 2026-05-25. Reflects Laravel 12, Livewire 4.3, Bootstrap 5 + Vite 7
> frontend, and migrations through `2026_05_25_190000_add_gateway_column_to_payments`.

## System Requirements
- PHP 8.2+ with extensions: BCMath, Ctype, Fileinfo, GD (or Imagick — required by
  `intervention/image`), Intl, JSON, Mbstring, OpenSSL, PDO, Tokenizer, XML,
  Zip (required by `maatwebsite/excel`), Redis
- MySQL 8.0+ or MariaDB 10.6+
- Redis 6.0+ (used for cache, sessions, queues, and broadcasting state)
- Node.js 20+ / npm (Vite 7 + Sass build)
- Composer 2.x
- Nginx or Apache (LiteSpeed also works)
- SSL certificate (Let's Encrypt)
- Outbound HTTPS to: PayMongo, Stripe, Pusher/Reverb, Semaphore SMS, Mail/SMTP
  provider, AWS S3 (if used for media)

---

## 1. Server Setup (Ubuntu 22.04)

```bash
# Install PHP 8.2 with every extension CourtMaster needs
sudo add-apt-repository ppa:ondrej/php
sudo apt install php8.2-{fpm,mysql,redis,mbstring,xml,curl,zip,bcmath,gd,intl,opcache}

# Install MySQL 8
sudo apt install mysql-server-8.0

# Install Redis
sudo apt install redis-server

# Install Nginx
sudo apt install nginx

# Install Node.js
curl -fsSL https://deb.nodesource.com/setup_20.x | sudo -E bash -
sudo apt install nodejs
```

---

## 2. Application Setup

```bash
# Clone project
git clone https://github.com/your-org/courtmaster.git /var/www/courtmaster
cd /var/www/courtmaster

# Install PHP dependencies
composer install --no-dev --optimize-autoloader

# Install Node dependencies
npm ci

# Build assets (Vite 7 + Sass → public/build/)
npm run build

# Copy and configure .env
cp .env.example .env
php artisan key:generate

# Generate VAPID keys for Web Push (minishlink/web-push is a direct dep)
php artisan key:generate --show   # reference
# Use any VAPID generator (e.g. `npx web-push generate-vapid-keys`)
# Then set VAPID_PUBLIC_KEY, VAPID_PRIVATE_KEY, VAPID_SUBJECT in .env

# Configure .env with your production values
nano .env
```

---

## 3. Database Setup

```bash
# Create MySQL database
mysql -u root -p
CREATE DATABASE courtmaster CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'courtmaster_user'@'localhost' IDENTIFIED BY 'your_secure_password';
GRANT ALL PRIVILEGES ON courtmaster.* TO 'courtmaster_user'@'localhost';
FLUSH PRIVILEGES;
EXIT;

# Run migrations and seed
php artisan migrate --force
php artisan db:seed --force

# Storage link
php artisan storage:link
```

> **Heads-up on data migrations.** The 2026-05 batch includes several non-trivial
> changes — review before running on a populated DB:
> - `2026_05_23_120000_convert_credits_to_minutes` — rewrites existing
>   membership/wallet credit values from currency to minutes. Take a DB snapshot
>   first and verify in staging.
> - `2026_05_24_100000_align_cash_drawer_logs_schema` — restructures
>   `cash_drawer_logs`; back up any existing drawer history.
> - `2026_05_25_140000_create_refund_requests_table` adds a new approval-driven
>   refund flow used by `RefundRequestController`.
> - `2026_05_25_160000_add_payment_credentials_to_tenants` — per-tenant gateway
>   credentials (encrypted). Existing tenants pick up the global env keys until
>   overridden in tenant settings.

---

## 4. Nginx Configuration

```nginx
server {
    listen 80;
    server_name courtmaster.yourdomain.com;
    return 301 https://$host$request_uri;
}

server {
    listen 443 ssl http2;
    server_name courtmaster.yourdomain.com;

    ssl_certificate /etc/letsencrypt/live/courtmaster.yourdomain.com/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/courtmaster.yourdomain.com/privkey.pem;

    root /var/www/courtmaster/public;
    index index.php;

    client_max_body_size 50M;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.2-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
    }

    location ~* \.(css|js|png|jpg|jpeg|gif|ico|svg|woff|woff2)$ {
        expires 1y;
        add_header Cache-Control "public, no-transform";
    }

    # Service worker must not be cached aggressively (PWA push + offline)
    location = /sw.js {
        add_header Cache-Control "no-store, no-cache, must-revalidate";
        try_files $uri =404;
    }

    # PWA manifest
    location = /manifest.json {
        add_header Cache-Control "public, max-age=3600";
        try_files $uri =404;
    }

    add_header X-Frame-Options "SAMEORIGIN";
    add_header X-Content-Type-Options "nosniff";
    add_header X-XSS-Protection "1; mode=block";
    add_header Strict-Transport-Security "max-age=31536000; includeSubDomains" always;
    add_header Referrer-Policy "strict-origin-when-cross-origin";
}
```

---

## 5. Queue Workers (Supervisor)

Only two queues are used in code today: `default` (jobs, exports, billing,
broadcast events) and `notifications` (mail / SMS / push / database channels —
see `App\Listeners\Send*::$queue`).

```ini
# /etc/supervisor/conf.d/courtmaster-workers.conf

[program:courtmaster-default]
command=php /var/www/courtmaster/artisan queue:work redis --queue=default --sleep=3 --tries=3 --max-time=3600
directory=/var/www/courtmaster
user=www-data
numprocs=2
autostart=true
autorestart=true
stderr_logfile=/var/log/supervisor/courtmaster-default.err.log
stdout_logfile=/var/log/supervisor/courtmaster-default.out.log

[program:courtmaster-notifications]
command=php /var/www/courtmaster/artisan queue:work redis --queue=notifications --sleep=3 --tries=3
directory=/var/www/courtmaster
user=www-data
numprocs=2
autostart=true
autorestart=true
stderr_logfile=/var/log/supervisor/courtmaster-notifications.err.log
stdout_logfile=/var/log/supervisor/courtmaster-notifications.out.log

[program:courtmaster-scheduler]
command=php /var/www/courtmaster/artisan schedule:work
directory=/var/www/courtmaster
user=www-data
numprocs=1
autostart=true
autorestart=true
stderr_logfile=/var/log/supervisor/courtmaster-scheduler.err.log
stdout_logfile=/var/log/supervisor/courtmaster-scheduler.out.log
```

```bash
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl start all
```

### Scheduled jobs (defined in [routes/console.php](routes/console.php))

| Job | Cadence | Purpose |
|---|---|---|
| `AutoStartTimers` | every minute | Start timers when a confirmed booking's slot arrives |
| `CheckOvertimeTimers` | every minute | Auto-charge / alert on overtime sessions |
| `booking-starting-soon-notify` | every minute | Push notification ~1 min before start (skips if timer already running) |
| Booking reminder sweep | every 5 minutes | Dispatches `SendBookingReminder` 2 h before slot |
| `ProcessMembershipRenewals` | daily 01:00 | Renew / expire memberships |
| `ProcessBillingRetries` | daily 02:00 | Generate SaaS invoices, retry failed charges, suspend overdue tenants |
| `CheckLowStock` | daily 07:00 | Low-stock notifications per tenant |
| `Cache::flush()` | weekly | Bulk cache flush (be aware — see Cache Strategy) |
| `activitylog:clean --days=90` | monthly | Prune Spatie activity log |

If you prefer the OS cron over Supervisor's `schedule:work`, add:

```cron
* * * * * cd /var/www/courtmaster && php artisan schedule:run >> /dev/null 2>&1
```

---

## 6. Production Optimization

```bash
# Cache configuration
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache
php artisan icons:cache

# Optimize autoloader
composer dump-autoload --optimize

# Set permissions
sudo chown -R www-data:www-data /var/www/courtmaster/storage
sudo chown -R www-data:www-data /var/www/courtmaster/bootstrap/cache
sudo chmod -R 775 /var/www/courtmaster/storage
sudo chmod -R 775 /var/www/courtmaster/bootstrap/cache
```

---

## 7. SSL (Let's Encrypt)

```bash
sudo apt install certbot python3-certbot-nginx
sudo certbot --nginx -d courtmaster.yourdomain.com
sudo certbot renew --dry-run
```

---

## 8. Deployment Script

```bash
#!/bin/bash
# deploy.sh - Run after each git push

set -e
cd /var/www/courtmaster

git pull origin main
composer install --no-dev --optimize-autoloader
npm ci && npm run build

php artisan down --secret="maintenance-bypass-secret"
php artisan migrate --force
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache
php artisan queue:restart
php artisan up

echo "✅ Deploy complete!"
```

---

## 9. Cache Strategy

CourtMaster relies on Redis (or a compatible cache store) for hot-path reads.
What's cached, where, and how to invalidate it:

| Key prefix | Driver | TTL | Cleared by |
|---|---|---|---|
| `config:*` | file | until `cache:clear` | `php artisan config:clear` / `optimize:clear` |
| `routes:*` | file | until `route:clear`  | `php artisan route:clear` / deploy script |
| `views:*` | file | per template hash | `php artisan view:clear` |
| `permission_cache:*` (Spatie) | redis | 24h | Auto on role/permission change |
| `activitylog.batch:*` | redis | 24h | Auto-pruned by `activitylog:clean --days=90` (monthly) |
| `session:*` | redis | `SESSION_LIFETIME` (default 8h sliding) | User logout / device revoke |
| `queue:default` | redis | n/a | Worker consumes |
| `tenant.{id}.settings` (app-level) | redis | 5m | Manually on Tenant settings update |
| `analytics.overview.{tenantId}` (recommended) | redis | 5m | After payment/booking creation, or natural TTL |

### Recommendations
- **Never enable `config:cache` in development.** Routes can reference env vars at module load time.
- **Always run `php artisan optimize` in deploy.sh** (already wired). This reads config + routes + events into compiled PHP arrays.
- **Page cache** — not used; Bootstrap dashboards include per-user data so caching at the HTTP layer would leak across tenants.
- **Database query cache** — relied on by `withCount`/`withSum` aggregates in `AnalyticsService` (rebuilt per request). If `/admin/analytics/overview` becomes hot, wrap each method in `Cache::remember("analytics.{$tenantId}.{$method}", 300, fn () => ...)` and bust it from `BookingObserver` / `PaymentObserver`.
- **Weekly `Cache::flush()`** — `routes/console.php` calls this every Sunday. If you add long-lived cache entries (e.g. baked report data), key them in a separate Redis DB or replace the weekly flush with targeted `Cache::tags()->flush()` calls.
- **Redis persistence** — for production set `appendonly yes` in `redis.conf` so queued jobs survive a restart.
- **Driver choice** — composer requires `predis/predis`; `REDIS_CLIENT=predis` in `.env.example` matches. Don't switch to `phpredis` without flushing existing keys (serialization formats differ).

### Common cache pitfalls
- After editing route names: `php artisan route:clear`. The `views:clear` from this guide is unrelated.
- After editing Blade partials: `php artisan view:clear` (compiled views in `storage/framework/views/`).
- After deploying a new model trait that affects activity-log: clear `permission_cache` via `php artisan permission:cache-reset` (Spatie command).
- Redis can develop bad keys if you switch between `phpredis` and `predis` drivers — flush with `php artisan cache:clear` when migrating.

---

## 10. Production Checklist

### Required before going live

**Environment**
- [ ] `APP_ENV=production`, `APP_DEBUG=false`, `APP_URL=https://...`
- [ ] Strong `APP_KEY` generated (`php artisan key:generate --force`)
- [ ] `LOG_LEVEL=warning` (not `debug`)
- [ ] `SANCTUM_STATEFUL_DOMAINS` set to the production host(s)
- [ ] Feature flags reviewed (`FEATURE_2FA`, `FEATURE_SOCIAL_LOGIN`, `FEATURE_PWA`)
- [ ] All sensitive credentials moved out of `.env.example` defaults

**Database & storage**
- [ ] MySQL 8 (or compatible) credentials secured with non-root user
- [ ] All pending migrations run (`php artisan migrate --force`) — current
      head: `2026_05_25_190000_add_gateway_column_to_payments`
- [ ] **Staging dry-run** for the 2026-05 batch: `convert_credits_to_minutes`,
      `align_cash_drawer_logs_schema`, `add_payment_credentials_to_tenants`
- [ ] Seeders run for permissions + plans (`db:seed --class=PermissionSeeder`, `SubscriptionPlanSeeder`)
- [ ] Storage symlink created (`php artisan storage:link`)
- [ ] File permissions `775 storage/ bootstrap/cache/` owned by web user
- [ ] If `FILESYSTEM_DISK=s3`: `AWS_*` keys set, bucket exists, lifecycle policy
      for `media/` defined
- [ ] Daily DB dump + offsite sync (S3/Backblaze) configured
- [ ] At least one tested DB restore drill

**Cache & queues**
- [ ] Redis configured with `requirepass`
- [ ] `redis.conf` has `appendonly yes` for durability
- [ ] `CACHE_STORE=redis`, `SESSION_DRIVER=redis`, `QUEUE_CONNECTION=redis`
- [ ] Queue workers running via Supervisor (default + notifications queues)
- [ ] Scheduler cron entry installed: `* * * * * php artisan schedule:run`

**Web**
- [ ] Nginx running, gzip on, HTTP/2 enabled
- [ ] SSL certificate installed + auto-renewing (`certbot renew --dry-run` passes)
- [ ] Security headers: HSTS, X-Content-Type-Options, Referrer-Policy
- [ ] CORS configured in `config/cors.php` for the mobile/kiosk origins only

**Integrations**
- [ ] PayMongo `PAYMONGO_SECRET_KEY` + `PAYMONGO_WEBHOOK_SECRET` set
      (overridable per tenant after `add_payment_credentials_to_tenants`)
- [ ] Stripe `STRIPE_SECRET` + `STRIPE_WEBHOOK_SECRET` set
- [ ] Both gateways' webhook URLs pointed at `/api/v1/webhooks/{paymongo,stripe}`
- [ ] Pusher / Reverb keys set for broadcasting (`BROADCAST_CONNECTION=pusher`,
      `PUSHER_APP_ID/KEY/SECRET/CLUSTER`, plus `VITE_PUSHER_APP_KEY/CLUSTER` for
      the browser)
- [ ] Web Push (`VAPID_PUBLIC_KEY`, `VAPID_PRIVATE_KEY`, `VAPID_SUBJECT`) set —
      `minishlink/web-push` is a required dep; without keys the channel skips
      delivery silently
- [ ] SMTP (or Resend / SES / Postmark) configured + a test email sent
- [ ] SMS (Semaphore) `SEMAPHORE_API_KEY` set if SMS notifications are used
- [ ] Socialite (`GOOGLE_CLIENT_ID/SECRET`, `FACEBOOK_*`) optional, gated by
      `FEATURE_SOCIAL_LOGIN`
- [ ] Slack alerts (`LOG_SLACK_WEBHOOK_URL`, `SLACK_BOT_USER_OAUTH_TOKEN`)
      optional but recommended for error pipelines

**Security**
- [ ] Rate limiters enforced (already wired in `AppServiceProvider::boot()`):
  - login `5/min` per email+IP
  - otp-request `3/min`
  - password-reset `3/min`
  - payment-webhook `120/min` per IP
  - booking-create `20/min` per user
- [ ] CSRF/XSS protection (Laravel defaults — verify no `csrf` exemptions slipped into routes)
- [ ] Spatie Permission cache enabled
- [ ] 2FA encrypted secret column is TEXT (migration `2026_05_21_120600` applied)
- [ ] Refund-request approval flow live (migration
      `2026_05_25_140000_create_refund_requests_table`) — confirm super-admin
      review queue is being watched
- [ ] Per-tenant payment credentials encrypted (handled by `Crypt::encryptString`
      via `add_payment_credentials_to_tenants`)
- [ ] Default seeded passwords **changed** (`admin@courtmaster.app`, etc.)

**Observability**
- [ ] Log rotation in `/etc/logrotate.d/courtmaster` (daily, 14-day keep)
- [ ] Sentry DSN configured **OR** Laravel Telescope locked behind super-admin only
- [ ] Uptime monitor (UptimeRobot, BetterStack) hitting `/up` health route
- [ ] Slack/email alert on `LOG_LEVEL=error` lines

**SaaS billing**
- [ ] Subscription scheduler (`ProcessBillingRetries` at 02:00 daily) running
- [ ] Subscription `payment_method_token` is encrypted at rest (handled by `Crypt::encryptString`)
- [ ] Test invoice → retry → suspend cycle in staging before launch

**Performance hardening (after launch)**
- [ ] Run `php artisan optimize` in `deploy.sh` (config:cache, route:cache, view:cache, event:cache)
- [ ] OPCache enabled (`opcache.enable=1`, `opcache.validate_timestamps=0` in prod)
- [ ] MySQL `innodb_buffer_pool_size` sized to ~70% of available RAM
- [ ] Add indexes on `bookings.booking_date`, `bookings.tenant_id`,
      `bookings.payment_method`, `payments.gateway`, `payments.gateway_reference`
      (verify via `EXPLAIN`)
- [ ] CDN (Cloudflare/Bunny) in front of `/build/*` and `/icons/*`
- [ ] Set `OCTANE_SERVER=swoole` and run via Octane only after profiling reveals PHP-FPM is the bottleneck

---

## 11. Default Login Credentials

> **Super Admin:** admin@courtmaster.app / password
> **Demo Owner:** owner@demo.courtmaster.app / password
> **Demo Staff:** staff@demo.courtmaster.app / password
> **Demo Player:** player@demo.courtmaster.app / password

⚠️ **Change all default passwords immediately in production!**

---

## 12. Rollback Playbook

If a deploy goes bad:

```bash
# 1. Stop accepting new work
sudo supervisorctl stop courtmaster-workers:*
sudo supervisorctl stop courtmaster-scheduler

# 2. Roll back code
cd /var/www/courtmaster
git log --oneline -10           # find the last good commit
git reset --hard <commit-sha>
composer install --no-dev --optimize-autoloader
npm ci && npm run build

# 3. Roll back migrations only if the new migration is the cause
php artisan migrate:rollback --step=1

# 4. Rebuild compiled state and reload
php artisan optimize:clear
php artisan optimize
sudo systemctl reload php8.2-fpm
sudo systemctl reload nginx

# 5. Resume workers
sudo supervisorctl start courtmaster-workers:*
sudo supervisorctl start courtmaster-scheduler
```

**Database migrations are usually NOT rolled back.** Restore from the latest DB
snapshot instead and re-apply only the migrations that were known-good before
the failed deploy.
