# Runs the Android app against this laptop over Wi-Fi or a phone hotspot.
# Finds the laptop's current address, checks the network, firewall and server,
# then starts `flutter run` with that address. See docs/phone-testing.md.
#
#   powershell -ExecutionPolicy Bypass -File deploy\phone-wifi.ps1           # check, then run the app
#   powershell -ExecutionPolicy Bypass -File deploy\phone-wifi.ps1 -NoRun    # check only
param([int]$Port = 0, [switch]$NoRun)

$root = Split-Path $PSScriptRoot -Parent
if (-not $Port) {
    $m = Select-String -Path (Join-Path $root '.env') -Pattern '^HTTP_PORT=(\d+)' -ErrorAction SilentlyContinue | Select-Object -First 1
    $Port = if ($m) { [int]$m.Matches.Groups[1].Value } else { 8080 }
}

# The physical adapter with a default gateway is the one on the Wi-Fi or hotspot
# (VMware, WSL and VPN adapters such as Radmin are virtual).
$physical = (Get-NetAdapter -Physical | Where-Object Status -eq 'Up').InterfaceAlias
$net = Get-NetIPConfiguration | Where-Object { $_.IPv4DefaultGateway -and $physical -contains $_.InterfaceAlias } | Select-Object -First 1
if (-not $net) { throw 'Not connected to any network. Join the Wi-Fi or hotspot first.' }
$ip = ($net.IPv4Address | Select-Object -First 1).IPAddress
$alias = $net.InterfaceAlias
$ok = $true

Write-Host "Network : $($net.NetProfile.Name) ($alias)"
Write-Host "Address : $ip   Port: $Port"

$category = (Get-NetConnectionProfile -InterfaceAlias $alias).NetworkCategory
if ($category -ne 'Private') {
    $ok = $false
    Write-Host "[!] This network is '$category'. Windows blocks the phone on Public networks." -ForegroundColor Yellow
    Write-Host "    Fix (Administrator PowerShell): Set-NetConnectionProfile -InterfaceAlias '$alias' -NetworkCategory Private"
}

$allowed = Get-NetFirewallPortFilter -Protocol TCP -ErrorAction SilentlyContinue |
    Where-Object { @($_.LocalPort) -contains "$Port" } | Get-NetFirewallRule -ErrorAction SilentlyContinue |
    Where-Object { $_.Enabled -eq 'True' -and $_.Direction -eq 'Inbound' -and $_.Action -eq 'Allow' }
if (-not $allowed) {
    Write-Host "[?] No firewall rule found that allows port $Port. If the phone cannot open the health page, add one." -ForegroundColor Yellow
    Write-Host "    Fix (Administrator PowerShell, once): New-NetFirewallRule -DisplayName 'Anecnotes dev ($Port)' -Direction Inbound -Protocol TCP -LocalPort $Port -Action Allow -Profile Private"
}

try {
    $h = Invoke-RestMethod "http://${ip}:$Port/api/health" -TimeoutSec 5
    Write-Host "Server  : $($h.status) (commit $($h.commit))"
} catch {
    $ok = $false
    Write-Host "[!] The server does not answer on http://${ip}:$Port. Is Docker Desktop running? Try: docker compose --profile quicktunnel up -d" -ForegroundColor Yellow
}

Write-Host ""
Write-Host "On the phone, open: http://${ip}:$Port/api/health"
$api = "http://${ip}:$Port/api/v1"
if ($NoRun) { Write-Host "App build flag: --dart-define=API_BASE_URL=$api"; return }
if (-not $ok) { Write-Host 'Fix the [!] items above, then run this again.' -ForegroundColor Yellow; exit 1 }

Push-Location (Join-Path $root 'frontend')
try { flutter run --dart-define=API_BASE_URL=$api } finally { Pop-Location }
