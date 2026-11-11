@echo off
setlocal EnableExtensions
REM ============================================================
REM  Prosthetics - FIRST INSTALL on a computer with Laragon (PHP + MySQL).
REM  Start Laragon (Start All) first, then double-click this file.
REM  Default folder: C:\laragon\www\prosthetics   Other: INSTALL.bat "D:\folder"
REM ============================================================
cd /d "%~dp0"
set "PKG=%~dp0files"
set "APP=C:\laragon\www\prosthetics"
if not "%~1"=="" set "APP=%~1"

echo.
echo  Prosthetics - first install
type "%~dp0VERSION.txt"
echo  Install folder: %APP%
echo.

if not exist "%PKG%\artisan" (
  echo ERROR: package is incomplete - extract the whole ZIP first.
  goto fail
)
if exist "%APP%\.env" (
  echo The system is already installed in %APP% - use UPDATE.bat instead.
  goto fail
)
where php >nul 2>&1
if errorlevel 1 (
  echo ERROR: php was not found. Install/start Laragon, then run this file from the Laragon terminal.
  goto fail
)
php -r "exit(version_compare(PHP_VERSION, '8.3.0', '>=') ? 0 : 1);"
if errorlevel 1 (
  echo ERROR: this version needs PHP 8.3 or newer. In Laragon: Menu - PHP - Version - choose 8.3+
  php -v
  goto fail
)

set "DBHOST=127.0.0.1"
set "DBPORT=3306"
set "DBNAME=prosthetics"
set "DBUSER=root"
set "DBPASS="
set /p "DBNAME=Database name [prosthetics]: "
set /p "DBUSER=MySQL user [root]: "
set /p "DBPASS=MySQL password [empty for Laragon]: "
:askpw
set "ADMINPW="
set /p "ADMINPW=Choose the password for the main account (superadmin), 8+ characters: "
if "%ADMINPW%"=="" goto askpw

echo [1/7] Copying program files...
robocopy "%PKG%" "%APP%" /E /NFL /NDL /NJH /NJS /NP /R:2 /W:2
if errorlevel 8 goto fail

echo [2/7] Creating database...
php "%~dp0tools\create-database.php" "%DBHOST%" "%DBPORT%" "%DBNAME%" "%DBUSER%" "%DBPASS%"
if errorlevel 1 goto fail

echo [3/7] Writing settings (.env)...
copy /y "%APP%\.env.example" "%APP%\.env" >nul
powershell -NoProfile -Command "$p='%APP%\.env'; $l=[IO.File]::ReadAllLines($p); $l=$l -replace '^DB_CONNECTION=.*','DB_CONNECTION=mysql' -replace '^DB_HOST=.*','DB_HOST=%DBHOST%' -replace '^DB_PORT=.*','DB_PORT=%DBPORT%' -replace '^DB_DATABASE=.*','DB_DATABASE=%DBNAME%' -replace '^DB_USERNAME=.*','DB_USERNAME=%DBUSER%' -replace '^DB_PASSWORD=.*','DB_PASSWORD=%DBPASS%' -replace '^SESSION_SECURE_COOKIE=.*','SESSION_SECURE_COOKIE=false'; [IO.File]::WriteAllLines($p,$l)"
if errorlevel 1 goto fail

pushd "%APP%"
php artisan key:generate --force
if errorlevel 1 ( popd & goto fail )

echo [4/7] Creating tables...
php artisan migrate --force
if errorlevel 1 ( popd & goto fail )

echo [5/7] Basic data (roles, main account, categories)...
set "SEED_SUPER_ADMIN_PASSWORD=%ADMINPW%"
for %%S in (RolesAndAdminSeeder MilitaryRankSeeder VisitTypeSeeder StockCategorySeeder) do (
  php artisan db:seed --class=%%S --force
  if errorlevel 1 ( popd & goto fail )
)
set "SEED_SUPER_ADMIN_PASSWORD="
php artisan prosthetics:sync-permissions

echo [6/7] Storage link and caches...
php artisan storage:link >nul 2>&1
php artisan optimize:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache
popd

echo [7/7] Done.
echo.
echo ============================================================
echo  INSTALLED. Open the system in the browser (Laragon - Web) and log in:
echo     username: superadmin
echo     password: the one you just chose
echo  Future updates: extract the new package and double-click UPDATE.bat
echo ============================================================
pause
exit /b 0

:fail
echo.
echo INSTALL STOPPED - take a photo of this screen and send it.
pause
exit /b 1
