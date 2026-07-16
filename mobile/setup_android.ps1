# Quick Android SDK check for Flutter parent app
Write-Host "=== Flutter Android Setup Check ===" -ForegroundColor Cyan

$sdk = "$env:LOCALAPPDATA\Android\Sdk"
if (-not (Test-Path $sdk)) {
    Write-Host "[X] Android SDK not found at: $sdk" -ForegroundColor Red
    Write-Host ""
    Write-Host "Install Android Studio first:" -ForegroundColor Yellow
    Write-Host "  winget install Google.AndroidStudio --accept-package-agreements --accept-source-agreements"
    Write-Host ""
    Write-Host "Then open Android Studio once to finish SDK download."
    Write-Host "Full guide: mobile\ANDROID_SETUP.md"
    exit 1
}

Write-Host "[OK] Android SDK found: $sdk" -ForegroundColor Green
flutter config --android-sdk $sdk
flutter doctor -v

Write-Host ""
Write-Host "Accept SDK licenses if prompted:" -ForegroundColor Yellow
flutter doctor --android-licenses

Write-Host ""
Write-Host "Connected devices:" -ForegroundColor Cyan
flutter devices

Write-Host ""
Write-Host "When your phone appears, run:" -ForegroundColor Green
Write-Host "  flutter pub get"
Write-Host "  flutter run"
