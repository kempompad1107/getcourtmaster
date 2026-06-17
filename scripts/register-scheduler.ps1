# Registers the CourtMaster scheduler as a Windows task that fires every minute.
# Run ONCE, in an elevated (Administrator) PowerShell:
#     powershell -ExecutionPolicy Bypass -File scripts\register-scheduler.ps1
#
# This single task drives BOTH the Laravel scheduler and the queue (queue:work
# is scheduled inside routes/console.php), so no separate queue worker service
# is needed. To remove it later:
#     Unregister-ScheduledTask -TaskName "CourtMaster Scheduler" -Confirm:$false

$bat = "C:\xampp\htdocs\courtmaster\scripts\courtmaster-scheduler.bat"
$action  = New-ScheduledTaskAction -Execute $bat
$trigger = New-ScheduledTaskTrigger -Once -At (Get-Date) `
           -RepetitionInterval (New-TimeSpan -Minutes 1) `
           -RepetitionDuration ([TimeSpan]::MaxValue)
$settings = New-ScheduledTaskSettingsSet -StartWhenAvailable -MultipleInstances IgnoreNew

Register-ScheduledTask -TaskName "CourtMaster Scheduler" `
    -Action $action -Trigger $trigger -Settings $settings `
    -Description "Runs 'php artisan schedule:run' every minute (drives CourtMaster scheduler + queue draining)." `
    -Force

Write-Host "Registered 'CourtMaster Scheduler'. Verify with: Get-ScheduledTask -TaskName 'CourtMaster Scheduler'"
