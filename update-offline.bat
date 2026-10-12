@echo off
REM ============================================================
REM  One-click update for the offline Laragon server.
REM  Double-click this file: backup, pull latest code, update the
REM  database and permissions, clear caches.
REM ============================================================
cd /d "%~dp0"

echo [1/6] Backup before update...
php artisan prosthetics:backup
if errorlevel 1 echo    WARNING: backup did not finish - continuing.

echo [2/6] Pulling latest code...
git pull origin master
if errorlevel 1 goto fail

echo [3/6] PHP packages...
call composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader
if errorlevel 1 echo    WARNING: composer did not finish - send a photo if the app shows an error.

echo [4/6] Updating database...
php artisan migrate --force
if errorlevel 1 goto fail

echo [5/6] Syncing new permissions...
php artisan prosthetics:sync-permissions
if errorlevel 1 goto fail

echo [6/6] Clearing caches...
php artisan optimize:clear

echo.
echo DONE - update finished. Ask users to log out and log in again.
pause
exit /b 0

:fail
echo.
echo UPDATE STOPPED - take a photo of this screen and send it.
pause
exit /b 1
