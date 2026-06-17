@echo off
REM CourtMaster scheduler tick. Registered as a Windows Task Scheduler job that
REM fires every minute; Laravel's scheduler decides what actually runs.
REM This also drains the database queue (queue:work is scheduled in console.php),
REM so NO separate queue:work service is required.
cd /d C:\xampp\htdocs\courtmaster
C:\xampp\php\php.exe artisan schedule:run >> storage\logs\scheduler.log 2>&1
