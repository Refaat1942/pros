@echo off
setlocal EnableExtensions
REM ============================================================
REM  Prosthetics - OFFLINE UPDATE (no internet, no git, no composer)
REM  Double-click this file. Default app folder: C:\laragon\www\prosthetics
REM  Other folder:  UPDATE.bat "D:\path\to\prosthetics"
REM  Keeps: .env, storage (uploads, backups, logs), public\storage.
REM ============================================================
cd /d "%~dp0"
set "PKG=%~dp0files"
set "APP=C:\laragon\www\prosthetics"
if not "%~1"=="" set "APP=%~1"
set "APPDOWN="

echo.
echo  Prosthetics offline update
type "%~dp0VERSION.txt"
echo  Target folder: %APP%
echo.

if not exist "%PKG%\artisan" (
  echo ERROR: package is incomplete - extract the whole ZIP first, then run UPDATE.bat from inside it.
  goto fail
)
if not exist "%APP%\artisan" (
  echo ERROR: the system was not found in %APP%
  echo        For a NEW computer use INSTALL.bat. For another folder: UPDATE.bat "D:\folder"
  goto fail
)
if not exist "%APP%\.env" (
  echo ERROR: %APP%\.env is missing - the system is not configured on this computer.
  goto fail
)
where php >nul 2>&1
if errorlevel 1 (
  echo ERROR: php was not found. Start Laragon, then run this file from the Laragon terminal
  echo        or right-click Laragon - Terminal, and type:  "%~f0"
  goto fail
)
php -r "exit(version_compare(PHP_VERSION, '8.3.0', '>=') ? 0 : 1);"
if errorlevel 1 (
  echo ERROR: this version needs PHP 8.3 or newer. In Laragon: Menu - PHP - Version - choose 8.3+
  php -v
  goto fail
)

echo [1/7] Backup before update...
pushd "%APP%"
php artisan prosthetics:backup
if errorlevel 1 (
  echo WARNING: backup did not finish.
  choice /C YN /M "Continue WITHOUT a backup"
  if errorlevel 2 ( popd & goto fail )
)
php artisan down >nul 2>&1
set "APPDOWN=1"
popd

echo [2/7] Copying program files...
for %%D in (app config database lang resources routes vendor) do (
  if exist "%PKG%\%%D" (
    robocopy "%PKG%\%%D" "%APP%\%%D" /MIR /XF *.sqlite *.sqlite-journal *.sqlite-wal /NFL /NDL /NJH /NJS /NP /R:2 /W:2
    if errorlevel 8 goto copyfail
  )
)
robocopy "%PKG%\bootstrap" "%APP%\bootstrap" /E /XD cache /NFL /NDL /NJH /NJS /NP /R:2 /W:2
if errorlevel 8 goto copyfail
REM public: add/replace only - never delete (uploaded thumbnails may live there).
robocopy "%PKG%\public" "%APP%\public" /E /XD storage /NFL /NDL /NJH /NJS /NP /R:2 /W:2
if errorlevel 8 goto copyfail
robocopy "%PKG%" "%APP%" artisan composer.json composer.lock VERSION.txt .env.example /NFL /NDL /NJH /NJS /NP /R:2 /W:2
if errorlevel 8 goto copyfail
REM Old compiled caches may point at removed classes - Laravel rebuilds them.
del /q "%APP%\bootstrap\cache\*.php" >nul 2>&1
if not exist "%APP%\storage\framework\views" mkdir "%APP%\storage\framework\views"
if not exist "%APP%\storage\framework\cache\data" mkdir "%APP%\storage\framework\cache\data"
if not exist "%APP%\storage\framework\sessions" mkdir "%APP%\storage\framework\sessions"
if not exist "%APP%\storage\logs" mkdir "%APP%\storage\logs"

pushd "%APP%"
echo [3/7] Updating database...
php artisan migrate --force
if errorlevel 1 ( popd & goto fail )

echo [4/7] Syncing permissions...
php artisan prosthetics:sync-permissions
if errorlevel 1 ( popd & goto fail )

echo [5/7] Storage link...
if not exist "%APP%\public\storage" php artisan storage:link >nul 2>&1

echo [6/7] Rebuilding caches...
php artisan optimize:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache

echo [7/7] Bringing the system back online...
php artisan up
set "APPDOWN="
popd

echo.
echo ============================================================
echo  DONE - update installed. Ask users to press Ctrl+F5 and log in again.
echo ============================================================
pause
exit /b 0

:copyfail
echo ERROR: copying files failed (is a file open or locked?). Close Laragon editors and try again.

:fail
if defined APPDOWN (
  pushd "%APP%"
  php artisan up >nul 2>&1
  popd
)
echo.
echo UPDATE STOPPED - take a photo of this screen and send it.
pause
exit /b 1
