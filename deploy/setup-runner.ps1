# Registers this Windows machine as the GitHub Actions self-hosted runner that deploys Anecnotes.
#
# Run once in a normal (not Administrator) PowerShell window, from the repository root:
#   powershell -ExecutionPolicy Bypass -File deploy\setup-runner.ps1
#
# Needs: Docker Desktop running, and the GitHub CLI signed in (gh auth status).
# The runner runs in your own session (not as a service) so it can reach Docker Desktop.
# Start it again after a reboot with:  C:\actions-runner\run.cmd

$ErrorActionPreference = 'Stop'
$repo = 'Ennius5/itsd85-final-bsit4cx-anecnotes'
$dir = 'C:\actions-runner'
$envDir = 'C:\anecnotes'

# 1. Production .env, kept only on this machine.
if (-not (Test-Path "$envDir\prod.env")) {
    New-Item -ItemType Directory -Force $envDir | Out-Null
    Copy-Item .env "$envDir\prod.env"
    Write-Host "Created $envDir\prod.env from your local .env. Edit it for production (APP_ENV=production, real AI keys)."
}

# 2. Download the latest runner.
$release = Invoke-RestMethod https://api.github.com/repos/actions/runner/releases/latest
$asset = $release.assets | Where-Object { $_.name -like 'actions-runner-win-x64-*.zip' } | Select-Object -First 1
New-Item -ItemType Directory -Force $dir | Out-Null
if (-not (Test-Path "$dir\config.cmd")) {
    Write-Host "Downloading $($asset.name)..."
    Invoke-WebRequest $asset.browser_download_url -OutFile "$dir\runner.zip"
    Expand-Archive "$dir\runner.zip" -DestinationPath $dir -Force
    Remove-Item "$dir\runner.zip"
}

# 3. Register with a short-lived token from the GitHub API.
$token = gh api -X POST "repos/$repo/actions/runners/registration-token" --jq .token
Push-Location $dir
.\config.cmd --unattended --url "https://github.com/$repo" --token $token `
    --name "$env:COMPUTERNAME-anecnotes" --labels "windows,anecnotes" --work _work --replace
Pop-Location

# 4. Turn on automatic deployment.
gh variable set DEPLOY_ENABLED --repo $repo --body true

Write-Host ""
Write-Host "Done. Start the runner now (keep the window open):  $dir\run.cmd"
Write-Host "Tip: put a shortcut to run.cmd in shell:startup so it starts when you log in."
