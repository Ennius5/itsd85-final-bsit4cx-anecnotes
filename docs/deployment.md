# Deployment

**Hosting option A** (§4.3): Docker Compose on the developer's laptop (Windows 10, Docker Desktop with WSL2), published through a Cloudflare Tunnel. GitHub Actions deploys automatically on every merge to `main` through a self-hosted runner on the same laptop.

```
merge to main ──► GitHub Actions: backend + frontend checks ──► deploy job (self-hosted runner, this laptop)
                                                                   ├─ copy C:\anecnotes\prod.env to .env
                                                                   ├─ docker compose up -d --build   (APP_VERSION = commit)
                                                                   ├─ php artisan db:seed            (demo accounts)
                                                                   └─ wait until /api/health reports the commit
```

## Public URL

| Phase | Tunnel | URL |
|---|---|---|
| M3 (now) | Quick tunnel (`quicktunnel` profile), no account | Random `https://*.trycloudflare.com`, shown in the deploy job summary. It changes only when the tunnel container is recreated (reboot, Docker restart). |
| Before M4 | Named tunnel (`tunnel` profile) on a free Cloudflare account with a domain | Fixed address for the README and the evaluation week |

Switch to the named tunnel by setting `CLOUDFLARE_TUNNEL_TOKEN` in `C:\anecnotes\prod.env` and the repository variable `TUNNEL_PROFILE=tunnel`.

## One-time setup on the server

1. Docker Desktop running; GitHub CLI signed in (`gh auth status`).
2. From the repository root, in a normal PowerShell window:
   ```powershell
   powershell -ExecutionPolicy Bypass -File deploy\setup-runner.ps1
   ```
   This creates `C:\anecnotes\prod.env` (edit it: `APP_ENV=production`, `APP_DEBUG=false`, a new `DB_PASSWORD`, the real AI keys), registers the runner with the labels `windows, anecnotes`, and sets `DEPLOY_ENABLED=true`.
3. Start the runner and leave it running: `C:\actions-runner\run.cmd`. A shortcut in `shell:startup` starts it at login.

The runner runs in the user's session, not as a Windows service, because Docker Desktop's engine is only reachable from the signed-in user.

## Security

- Secrets stay in `C:\anecnotes\prod.env` on the server; GitHub never stores them.
- The repository is public, so workflow runs from outside contributors' pull requests need the owner's approval (Settings → Actions → "Require approval for all external contributors"). Without this, a fork could run code on the self-hosted runner.
- Only Caddy publishes a port (8080 on the host). The database and API are reachable only inside the Docker network.

## Manual commands

```powershell
docker compose --profile quicktunnel up -d --build      # start or update
docker compose logs quicktunnel | Select-String trycloudflare   # find the public URL
docker compose exec api php artisan db:seed --force      # (re)load demo accounts
docker compose ps
```
