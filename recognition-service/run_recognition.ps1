# Start recognition cleanly (stops duplicate copies that freeze the Tapo camera).
$here = Split-Path -Parent $MyInvocation.MyCommand.Path
Set-Location $here

Write-Host "Stopping old recognition processes..." -ForegroundColor Cyan
Get-CimInstance Win32_Process -Filter "Name='python.exe'" |
    Where-Object { $_.CommandLine -match 'recognize\.py|stream_server\.py' } |
    ForEach-Object {
        Write-Host "  stop PID $($_.ProcessId)"
        Stop-Process -Id $_.ProcessId -Force -ErrorAction SilentlyContinue
    }

Start-Sleep -Milliseconds 400
if (Test-Path ".\.recognize.lock") {
    Remove-Item ".\.recognize.lock" -Force
}

Write-Host "Starting recognition (q / Esc / close window to quit)..." -ForegroundColor Green
& ".\.venv\Scripts\python.exe" -u recognize.py
