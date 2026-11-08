@echo off
rem ==========================================================================
rem  Updates the Antrian app on the Mini PC to the latest code.
rem  Run as administrator (it restarts the php-cgi workers, which run as SYSTEM).
rem
rem  Also used once during first-time setup (see SETUP-GUIDE.txt).
rem ==========================================================================

set "APP_DIR=C:\antrian"
set "NGINX_DIR=C:\nginx"
set "NGINX_PREFIX=%NGINX_DIR:\=/%/"
set "NGINX_CONF=%APP_DIR:\=/%/deploy/windows/nginx.conf"

cd /d "%APP_DIR%" || goto fail

echo [1/6] Pulling latest code...
git pull --ff-only || goto fail

echo [2/6] Installing PHP packages...
call composer install --no-dev --optimize-autoloader --no-interaction || goto fail

echo [3/6] Installing JS packages...
call npm ci || goto fail

echo [4/6] Building frontend...
call npm run build || goto fail
if exist "public\hot" del "public\hot"

echo [5/6] Migrating database and caching config...
php artisan migrate --force || goto fail
php artisan optimize || goto fail

echo [6/6] Reloading nginx and restarting PHP workers...
tasklist /fi "imagename eq nginx.exe" /nh | find /i "nginx.exe" >nul && (
    pushd "%NGINX_DIR%"
    nginx.exe -p "%NGINX_PREFIX%" -c "%NGINX_CONF%" -s reload
    popd
)
rem The watchdog in start-server.bat starts fresh workers within ~10 seconds.
taskkill /f /im php-cgi.exe >nul 2>&1

echo.
echo Update finished.
echo Restart the Mini PC so the TV and kiosk load the new version.
pause
exit /b 0

:fail
echo.
echo *** Update FAILED at the step above. The running app was not restarted. ***
pause
exit /b 1
