# Running the Scheduler & Queue Worker (Windows)

CourtMaster needs two background processes running in production:

| Process | Command | How often |
|---------|---------|-----------|
| **Scheduler** | `php artisan schedule:run` | Every **1 minute** |
| **Queue worker** | `php artisan queue:work` | **Always on** (long-running) |

This guide covers the recommended Windows setup. Paths assume the standard XAMPP install on this machine:

- PHP: `c:\xampp\php\php.exe`
- Project: `c:\xampp\htdocs\courtmaster`

> If your install differs, adjust the paths in every command/script below.

---

## Part 1 — The Scheduler (every minute via Task Scheduler)

Laravel's scheduler is designed to be triggered **once per minute**, and it internally decides which jobs are actually due. So you create **one** repeating task.

### Option A — Wrapper script + Task Scheduler (recommended)

**1. Create the wrapper script** `c:\xampp\htdocs\courtmaster\run-scheduler.bat`:

```bat
@echo off
cd /d c:\xampp\htdocs\courtmaster
c:\xampp\php\php.exe artisan schedule:run >> storage\logs\scheduler.log 2>&1
```

**2. Register the task** (run PowerShell **as Administrator**):

```powershell
$action  = New-ScheduledTaskAction -Execute "c:\xampp\htdocs\courtmaster\run-scheduler.bat"
$trigger = New-ScheduledTaskTrigger -Once -At (Get-Date) `
           -RepetitionInterval (New-TimeSpan -Minutes 1)
$settings = New-ScheduledTaskSettingsSet -StartWhenAvailable `
            -DontStopOnIdleEnd -ExecutionTimeLimit (New-TimeSpan -Minutes 5)

Register-ScheduledTask -TaskName "CourtMaster Scheduler" `
  -Action $action -Trigger $trigger -Settings $settings `
  -RunLevel Highest -Description "Runs Laravel schedule:run every minute"
```

> Run it under a specific user so it has DB/file access. Add
> `-User "DOMAIN\User" -Password "..."` to `Register-ScheduledTask`, or set the
> user later in the Task Scheduler GUI (**Run whether user is logged on or not**).

### Option B — Task Scheduler GUI (no PowerShell)

1. Open **Task Scheduler** → **Create Task** (not "Basic Task").
2. **General**: name `CourtMaster Scheduler`; check **Run whether user is logged on or not**; check **Run with highest privileges**.
3. **Triggers** → **New**: *Begin the task* = **On a schedule**, **One time**, then under *Advanced settings* check **Repeat task every 1 minute** for **Indefinitely**.
4. **Actions** → **New**: *Program/script* = `c:\xampp\php\php.exe`; *Add arguments* = `artisan schedule:run`; *Start in* = `c:\xampp\htdocs\courtmaster`.
5. **Settings**: check **Allow task to be run on demand** and **Run task as soon as possible after a scheduled start is missed**. Set *Stop the task if it runs longer than* to **1 hour** (or off).
6. Save (enter the user password when prompted).

### Verify the scheduler

```powershell
cd c:\xampp\htdocs\courtmaster
c:\xampp\php\php.exe artisan schedule:list      # shows registered scheduled commands
Get-Content storage\logs\scheduler.log -Tail 20 # see Option A output
```

---

## Part 2 — The Queue Worker (always running)

`queue:work` is a **long-running** process, not a per-minute task. You want it to:
start on boot, stay up, and **restart automatically** if it dies or after a deploy.

### Recommended: run it as a Windows Service with NSSM

[NSSM](https://nssm.cc/) (the "Non-Sucking Service Manager") is the simplest way to
keep a console process alive as a real Windows service.

**1. Install NSSM**

- Download from <https://nssm.cc/download>, unzip, and copy `nssm.exe` (the 64-bit
  one) somewhere on PATH, e.g. `c:\xampp\nssm.exe`. Or via Chocolatey: `choco install nssm`.

**2. Create the service** (PowerShell **as Administrator**):

```powershell
c:\xampp\nssm.exe install CourtMasterQueue `"c:\xampp\php\php.exe" `"artisan queue:work --sleep=3 --tries=3 --max-time=3600"

c:\xampp\nssm.exe set CourtMasterQueue AppDirectory  "c:\xampp\htdocs\courtmaster"
c:\xampp\nssm.exe set CourtMasterQueue AppStdout      "c:\xampp\htdocs\courtmaster\storage\logs\queue.log"
c:\xampp\nssm.exe set CourtMasterQueue AppStderr      "c:\xampp\htdocs\courtmaster\storage\logs\queue.log"
c:\xampp\nssm.exe set CourtMasterQueue Start          SERVICE_AUTO_START

c:\xampp\nssm.exe start CourtMasterQueue
```

Why those flags:
- `--max-time=3600` — worker exits cleanly every hour; NSSM restarts it (frees memory).
- `--tries=3` — a failed job is retried up to 3 times before going to `failed_jobs`.
- `--sleep=3` — when no jobs, wait 3s before polling again.

**Manage the service:**

```powershell
c:\xampp\nssm.exe restart CourtMasterQueue   # after deploying new code
c:\xampp\nssm.exe stop    CourtMasterQueue
c:\xampp\nssm.exe status  CourtMasterQueue
c:\xampp\nssm.exe remove  CourtMasterQueue confirm   # uninstall
```

> **After every deploy** run `php artisan queue:restart` (tells running workers to
> gracefully restart and pick up new code) **or** `nssm restart CourtMasterQueue`.
> A worker holds the old code in memory until restarted.

### Alternative: Task Scheduler "At startup"

If you can't install NSSM, create a `run-queue.bat`:

```bat
@echo off
cd /d c:\xampp\htdocs\courtmaster
:loop
c:\xampp\php\php.exe artisan queue:work --sleep=3 --tries=3 --max-time=3600 >> storage\logs\queue.log 2>&1
timeout /t 2 >nul
goto loop
```

Register it with trigger **At startup** (and **Run whether user is logged on or not**).
The `:loop` restarts the worker whenever `--max-time` expires or it crashes. This works
but NSSM gives you proper service control, so prefer NSSM when possible.

---

## Part 3 — "Cron job online" (cloud / no always-on Windows box)

If you'd rather not keep this machine running, use a remote cron service to hit a URL,
**but** Laravel's scheduler and queue can't be driven purely by an external pinger
unless you adapt them. Options:

### 3a. External cron service → scheduler endpoint
Services like **cron-job.org**, **EasyCron**, or **UptimeRobot** can call a URL every
minute. You'd expose a protected route that runs the due tasks, e.g.:

```php
// routes/web.php  — protect with a secret token!
Route::get('/cron/{token}', function (string $token) {
    abort_unless($token === config('app.cron_token'), 403);
    Artisan::call('schedule:run');
    return 'ok';
});
```

Then point the external cron at `https://your-domain/cron/YOUR_SECRET_TOKEN` every minute.
⚠️ This only works if the server is publicly reachable, and the token must be kept secret.
It does **not** replace the queue worker.

### 3b. Move queue to a managed/sync driver
- For **low volume**, set `QUEUE_CONNECTION=sync` in `.env` so jobs run immediately
  in-request (no worker needed). Trade-off: requests block until the job finishes.
- For real background processing without a local worker, host on a platform with a
  proper worker process (Laravel **Forge/Vapor**, Railway, Render, a Linux VPS with
  **Supervisor**, etc.). That's the standard production answer — Windows Task Scheduler +
  NSSM is the equivalent for staying on this box.

---

## Quick checklist

- [ ] `run-scheduler.bat` created
- [ ] "CourtMaster Scheduler" task runs every 1 min (check `storage\logs\scheduler.log`)
- [ ] NSSM service `CourtMasterQueue` installed and **Running**
- [ ] Both set to start automatically on boot
- [ ] Deploy step includes `php artisan queue:restart` (or `nssm restart`)
- [ ] `storage\logs\` is writable so the logs above actually get written
