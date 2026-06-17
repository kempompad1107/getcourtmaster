@echo off
cd /d c:\xampp\htdocs\courtmaster
:loop
c:\xampp\php\php.exe artisan queue:work --sleep=3 --tries=3 --max-time=3600 >> storage\logs\queue.log 2>&1
timeout /t 2 >nul
goto loop