# Start recognition cleanly (stops duplicate copies that freeze the Tapo camera).
$here = Split-Path -Parent $MyInvocation.MyCommand.Path
Set-Location $here

Write-Host "Stopping old recognition processes..."
Get-CimInstance Win32_Process -Filter "Name='python.exe'" |
    Where-Object { $_.CommandLine -match 'recognize\.py|stream_server\.py' } |
    ForEach-Object { Stop-Process -Id $_.ProcessId -Force -ErrorAction SilentlyContinue }

if (Test-Path ".\.recognize.lock") { Remove-Item ".\.recognize.lock" -Force }

Write-Host "Starting recognition..."
& ".\.venv\Scripts\python.exe" -u recognize.py
