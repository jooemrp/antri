@echo off
rem ==========================================================================
rem  Antrian server: starts the php-cgi workers and nginx, then keeps them
rem  alive. Every few seconds it checks both and restarts whatever has died.
rem
rem  It is started at boot by the "Antrian Server" scheduled task (install.ps1)
rem  and runs hidden as SYSTEM. It never exits on purpose.
rem  To stop everything: run stop-server.bat as administrator.
rem ==========================================================================

set "APP_DIR=C:\antrian"
set "PHP_DIR=C:\php"
set "NGINX_DIR=C:\nginx"

rem Must match the "upstream php" block in nginx.conf.
set "PHP_PORTS=9000 9001 9002 9003"
set "PHP_WORKERS=4"

set "CHECK_SECONDS=10"

rem php-cgi quits after 500 requests by default; 0 keeps it running.
set "PHP_FCGI_MAX_REQUESTS=0"

set "NGINX_PREFIX=%NGINX_DIR:\=/%/"
set "NGINX_CONF=%APP_DIR:\=/%/deploy/windows/nginx.conf"
set "LOG=%APP_DIR%\storage\logs\server-watchdog.log"

cd /d "%APP_DIR%"
>>"%LOG%" echo %date% %time% watchdog started

:loop
set "COUNT=0"
for /f %%c in ('tasklist /fi "imagename eq php-cgi.exe" /nh ^| find /c /i "php-cgi.exe"') do set "COUNT=%%c"
if %COUNT% LSS %PHP_WORKERS% call :restart_php

tasklist /fi "imagename eq nginx.exe" /nh | find /i "nginx.exe" >nul || call :start_nginx

ping -n %CHECK_SECONDS% 127.0.0.1 >nul
goto loop


:restart_php
>>"%LOG%" echo %date% %time% php-cgi: %COUNT% of %PHP_WORKERS% running, (re)starting all
taskkill /f /im php-cgi.exe >nul 2>&1
for %%p in (%PHP_PORTS%) do start "" /b "%PHP_DIR%\php-cgi.exe" -b 127.0.0.1:%%p -c "%PHP_DIR%\php.ini"
exit /b

:start_nginx
>>"%LOG%" echo %date% %time% nginx: not running, starting
pushd "%NGINX_DIR%"
start "" /b nginx.exe -p "%NGINX_PREFIX%" -c "%NGINX_CONF%"
popd
exit /b
