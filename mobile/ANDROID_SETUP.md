# Android Device Setup (Windows + Flutter)

Your phone will **not** appear in `flutter devices` until the **Android SDK** is installed.
`flutter doctor` currently reports: `Unable to locate Android SDK`.

## Quick fix (recommended): Install Android Studio

### Step 1 — Install Android Studio

**Option A — winget (PowerShell as Administrator):**
```powershell
winget install Google.AndroidStudio --accept-package-agreements --accept-source-agreements
```

**Option B — manual download:**  
https://developer.android.com/studio

### Step 2 — First launch wizard

1. Open **Android Studio**
2. Complete the setup wizard — it downloads the Android SDK automatically
3. Default SDK path: `C:\Users\<you>\AppData\Local\Android\Sdk`

### Step 3 — Tell Flutter where the SDK is

```powershell
flutter config --android-sdk "$env:LOCALAPPDATA\Android\Sdk"
flutter doctor --android-licenses
```
Press `y` to accept all licenses.

### Step 4 — Enable USB debugging on your phone

1. **Settings → About phone** → tap **Build number** 7 times (enables Developer options)
2. **Settings → Developer options** → enable **USB debugging**
3. Connect phone via USB cable (use a data cable, not charge-only)
4. On phone: tap **Allow** when prompted for USB debugging

### Step 5 — Verify device is detected

```powershell
cd C:\xampp\htdocs\attendance_system\mobile
flutter devices
```

You should see something like:
```
SM A125F (mobile) • R58N... • android-arm64 • Android 12 (API 31)
```

If the device shows **unauthorized**, unplug/replug USB and accept the prompt on the phone.

### Step 6 — Run the parent app on your phone

```powershell
cd C:\xampp\htdocs\attendance_system\mobile
flutter pub get
flutter run
```

Uses Railway API by default. Login: `parent01` / `Parent@123`

---

## Troubleshooting

| Problem | Fix |
|---------|-----|
| `Unable to locate Android SDK` | Install Android Studio, then `flutter config --android-sdk` |
| `adb` not recognized | Add `%LOCALAPPDATA%\Android\Sdk\platform-tools` to PATH |
| Phone not listed | Enable USB debugging; try another USB port/cable |
| `unauthorized` in adb | Revoke USB debugging authorizations on phone, reconnect |
| Samsung/Huawei driver issues | Install phone OEM USB driver or use Android Studio's driver |
| App can't reach API | `INTERNET` permission is in manifest; phone needs mobile data/Wi‑Fi |

### Add platform-tools to PATH (optional)

```powershell
[Environment]::SetEnvironmentVariable(
  "Path",
  $env:Path + ";$env:LOCALAPPDATA\Android\Sdk\platform-tools",
  "User"
)
```
Restart PowerShell after this.

---

## Build APK (install without USB)

```powershell
cd C:\xampp\htdocs\attendance_system\mobile
flutter build apk --release
```

APK output: `build\app\outputs\flutter-apk\app-release.apk`

Copy to phone and install (enable **Install unknown apps** for your file manager).

---

## Push notifications (optional, later)

FCM requires a Firebase project + `google-services.json`. In-app notifications work without push.
See `mobile/README.md` when you are ready to add Firebase.
