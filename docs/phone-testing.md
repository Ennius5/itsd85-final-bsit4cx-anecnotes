# Testing the Android app on a phone

The app reaches the server running on the laptop in one of two ways:

| Way | Use it when | Needs |
|---|---|---|
| **Wi-Fi or hotspot** | Phone and laptop are on the same network | Laptop's current address; rebuild when it changes |
| **USB cable** | No shared network, or Wi-Fi is blocked | Cable plugged in the whole time |

The port is `HTTP_PORT` in `.env` (8088 on the dev laptop, because VMware uses 8080).

## Every time: start the server

1. Open **Docker Desktop** and wait for "Engine running". The containers start by themselves.
2. Check: <http://localhost:8088/api/health> should show `"status":"ok"`.
   If not: `docker compose --profile quicktunnel up -d`

## Wi-Fi or phone hotspot

1. Connect the laptop to the network. For a hotspot, turn the hotspot on and join it from the laptop.
2. Plug the phone in by USB (only needed to install the app).
3. From the repo folder run:

   ```powershell
   powershell -ExecutionPolicy Bypass -File deploy\phone-wifi.ps1
   ```

   It finds the laptop's address on this network, checks the items below, and installs and starts the app.
   Add `-NoRun` to only run the checks.
4. Unplug the phone. The app keeps working while both stay on that network.

**When the network changes** (another Wi-Fi, the hotspot, or the router gives a new address), the app stops connecting. Run step 3 again.

### Fixes the script may ask for

Run these in an **Administrator** PowerShell (right-click Start → Windows PowerShell (Admin)).

- **Network is Public.** Windows treats new networks, hotspots included, as Public and blocks the phone. On a network you trust:

  ```powershell
  Set-NetConnectionProfile -InterfaceAlias "Wi-Fi" -NetworkCategory Private
  ```

- **Phone can't open the health page.** Usually Docker Desktop's own firewall permission is enough. If it isn't, allow the port once:

  ```powershell
  New-NetFirewallRule -DisplayName "Anecnotes dev (8088)" -Direction Inbound -Protocol TCP -LocalPort 8088 -Action Allow -Profile Private
  ```

### Test from the phone first

Open Chrome on the phone and go to the address the script prints, such as `http://192.168.1.14:8088/api/health`.
If that page loads, the app will connect. If it doesn't:

- Phone and laptop must be on the same network. A phone that is on mobile data, or a guest Wi-Fi, can't reach the laptop.
- Some routers and guest networks block devices from reaching each other ("AP/client isolation"). Use the hotspot or the USB cable instead.
- Turn off VPN apps on the phone.

## USB cable

```powershell
adb reverse tcp:8080 tcp:8088
cd frontend
flutter run
```

The app's default address is `http://localhost:8080`, and `adb reverse` forwards that to the laptop's port 8088. Run `adb reverse` again each time the cable is reconnected.
`adb` is in `D:\Astudio\SDK\platform-tools`.

## Notes

- Debug builds only: plain `http://` is allowed in debug builds. A release APK needs an `https://` address, which comes with the fixed domain (M4).
- The demo sign-in is in the README. The server only holds synthetic data.
- On the phone, the app is **Anecnotes** with ID `com.anecnotes.anecnotes`. Another app called `com.main.anecnotes` is an older, unrelated app.
