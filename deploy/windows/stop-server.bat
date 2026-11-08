@echo off
rem ==========================================================================
rem  Stops the Antrian server (watchdog, nginx, php-cgi).
rem  Run as administrator. Start it again with:
rem      schtasks /run /tn "Antrian Server"
rem  or just restart the PC.
rem ==========================================================================

rem End the watchdog first, otherwise it restarts what we kill below.
schtasks /end /tn "Antrian Server"
taskkill /f /im nginx.exe
taskkill /f /im php-cgi.exe
echo.
echo Antrian server stopped.
pause
