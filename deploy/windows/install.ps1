#Requires -RunAsAdministrator
<#
  One-time setup for the Antrian Mini PC. Run in an ADMINISTRATOR PowerShell,
  while logged in as the Windows user that will show the TV and kiosk:

      powershell -ExecutionPolicy Bypass -File C:\antrian\deploy\windows\install.ps1

  It is safe to run again; it replaces what it created before.

  What it does:
    1. "Antrian Server" task  - runs start-server.bat at boot, hidden, as SYSTEM
    2. "Antrian Screens" task - runs start-screens.bat when this user logs on
    3. Firewall rule          - allows other PCs on a Private network to reach port 80
    4. Power settings         - never sleep, never turn the screens off (on AC power)
#>

$ErrorActionPreference = 'Stop'

$AppDir = 'C:\antrian'
$Deploy = Join-Path $AppDir 'deploy\windows'
$User   = "$env:USERDOMAIN\$env:USERNAME"

foreach ($file in 'start-server.bat', 'start-screens.bat') {
    if (-not (Test-Path (Join-Path $Deploy $file))) {
        throw "Cannot find $Deploy\$file - is the repo cloned to $AppDir?"
    }
}

# 1. Server: at boot, before anyone logs in, no time limit.
$serverTask = @{
    TaskName  = 'Antrian Server'
    Action    = New-ScheduledTaskAction -Execute 'cmd.exe' -Argument "/c `"$Deploy\start-server.bat`"" -WorkingDirectory $AppDir
    Trigger   = New-ScheduledTaskTrigger -AtStartup
    Principal = New-ScheduledTaskPrincipal -UserId 'SYSTEM' -LogonType ServiceAccount -RunLevel Highest
    Settings  = New-ScheduledTaskSettingsSet -ExecutionTimeLimit ([TimeSpan]::Zero) -AllowStartIfOnBatteries `
                    -DontStopIfGoingOnBatteries -StartWhenAvailable `
                    -RestartCount 3 -RestartInterval (New-TimeSpan -Minutes 1)
}
Register-ScheduledTask @serverTask -Force | Out-Null
Write-Host "[ok] Task 'Antrian Server' (runs at boot as SYSTEM)"

# 2. Screens: when this user logs on, in their visible desktop session.
$screensTask = @{
    TaskName  = 'Antrian Screens'
    Action    = New-ScheduledTaskAction -Execute 'cmd.exe' -Argument "/c `"$Deploy\start-screens.bat`"" -WorkingDirectory $AppDir
    Trigger   = New-ScheduledTaskTrigger -AtLogOn -User $User
    Principal = New-ScheduledTaskPrincipal -UserId $User -LogonType Interactive -RunLevel Limited
    Settings  = New-ScheduledTaskSettingsSet -ExecutionTimeLimit (New-TimeSpan -Minutes 10) -AllowStartIfOnBatteries `
                    -DontStopIfGoingOnBatteries
}
Register-ScheduledTask @screensTask -Force | Out-Null
Write-Host "[ok] Task 'Antrian Screens' (runs when $User logs on)"

# 3. Firewall: port 80 inbound, Private networks only.
Get-NetFirewallRule -DisplayName 'Antrian HTTP' -ErrorAction SilentlyContinue | Remove-NetFirewallRule
New-NetFirewallRule -DisplayName 'Antrian HTTP' -Direction Inbound -Protocol TCP -LocalPort 80 `
    -Action Allow -Profile Private | Out-Null
Write-Host "[ok] Firewall rule 'Antrian HTTP' (TCP 80, Private networks)"

# 4. Power: never sleep or blank the screens while plugged in.
powercfg /change standby-timeout-ac 0
powercfg /change monitor-timeout-ac 0
powercfg /change hibernate-timeout-ac 0
Write-Host "[ok] Sleep and screen timeout disabled (AC power)"

foreach ($net in Get-NetConnectionProfile) {
    if ($net.NetworkCategory -ne 'Private') {
        Write-Warning "Network '$($net.Name)' is '$($net.NetworkCategory)'. Set it to Private, or other PCs cannot connect:"
        Write-Warning "  Set-NetConnectionProfile -InterfaceIndex $($net.InterfaceIndex) -NetworkCategory Private"
    }
}

Write-Host ''
Write-Host 'Done. Start the server now with:  schtasks /run /tn "Antrian Server"'
