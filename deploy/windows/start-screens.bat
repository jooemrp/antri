@echo off
rem ==========================================================================
rem  Opens the two full-screen Chrome windows on the Mini PC:
rem    TV     -> /display  (with sound allowed)
rem    Kiosk  -> /kiosk    (prints tickets without a print dialog)
rem
rem  Started at logon by the "Antrian Screens" scheduled task (install.ps1).
rem  Waits until the server answers before opening anything.
rem ==========================================================================

set "APP_URL=http://127.0.0.1"
set "CHROME=C:\Program Files\Google\Chrome\Application\chrome.exe"

rem A point on each screen, in pixels, as arranged in Settings > System > Display.
rem The main display starts at 0,0. Example: TV is main (1920 wide) and the
rem kiosk sits to its right -> KIOSK_X=1920.
set "TV_X=0"
set "TV_Y=0"
set "KIOSK_X=1920"
set "KIOSK_Y=0"

rem Separate Chrome profiles so the two windows are independent instances.
set "PROFILE_DIR=%LOCALAPPDATA%\Antrian"

rem Per-machine overrides (not in git, so `git pull` never conflicts).
rem Create screens.local.bat next to this file with e.g.:  set "KIOSK_X=2560"
if exist "%~dp0screens.local.bat" call "%~dp0screens.local.bat"

if not exist "%CHROME%" set "CHROME=C:\Program Files (x86)\Google\Chrome\Application\chrome.exe"
if not exist "%CHROME%" set "CHROME=%LOCALAPPDATA%\Google\Chrome\Application\chrome.exe"
if not exist "%CHROME%" (
    echo Google Chrome not found. Install Chrome or fix CHROME in this file.
    pause
    exit /b 1
)

echo Waiting for the Antrian server at %APP_URL% ...
set "TRIES=0"
:wait
curl.exe -s -o nul -f "%APP_URL%/display/state" && goto ready
set /a TRIES+=1
rem Give up waiting after ~2 minutes and open anyway, so an error is visible.
if %TRIES% GEQ 60 goto ready
ping -n 3 127.0.0.1 >nul
goto wait

:ready
set "COMMON=--no-first-run --no-default-browser-check --disable-features=Translate --hide-crash-restore-bubble --noerrdialogs"

start "" "%CHROME%" --user-data-dir="%PROFILE_DIR%\tv" --window-position=%TV_X%,%TV_Y% %COMMON% --autoplay-policy=no-user-gesture-required --kiosk "%APP_URL%/display"

rem Small gap so the two windows don't race for the same screen.
ping -n 3 127.0.0.1 >nul

start "" "%CHROME%" --user-data-dir="%PROFILE_DIR%\kiosk" --window-position=%KIOSK_X%,%KIOSK_Y% %COMMON% --kiosk-printing --disable-pinch --overscroll-history-navigation=0 --kiosk "%APP_URL%/kiosk"
