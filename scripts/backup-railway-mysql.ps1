# Backup Railway MySQL to a local .sql file (Windows + XAMPP mysqldump).
#
# 1) In Railway → MySQL service → Settings → Networking → enable Public Networking
#    (or TCP Proxy) and copy the public host + port.
# 2) MySQL service → Variables → copy MYSQLUSER, MYSQLPASSWORD, MYSQLDATABASE
# 3) Run this script and paste values when asked, OR set env vars first:
#
#    $env:RAILWAY_MYSQL_HOST="hayabusa.proxy.rlwy.net"
#    $env:RAILWAY_MYSQL_PORT="20000"
#    $env:RAILWAY_MYSQL_USER="root"
#    $env:RAILWAY_MYSQL_PASSWORD="(from Railway Variables — do not commit)"
#    $env:RAILWAY_MYSQL_DATABASE="railway"
#    .\scripts\backup-railway-mysql.ps1

$ErrorActionPreference = "Stop"

$mysql8Dump = "C:\Program Files\MySQL\MySQL Server 8.4\bin\mysqldump.exe"
$xamppDump = "C:\xampp\mysql\bin\mysqldump.exe"
$mysqldump = if (Test-Path $mysql8Dump) { $mysql8Dump } else { $xamppDump }
if (-not (Test-Path $mysqldump)) {
    Write-Host "mysqldump not found. Install MySQL 8 client (winget install Oracle.MySQL) or XAMPP." -ForegroundColor Red
    exit 1
}
if ($mysqldump -eq $xamppDump) {
    Write-Host "Using XAMPP mysqldump — Railway MySQL 8 may need: winget install Oracle.MySQL" -ForegroundColor Yellow
}

$hostName = $env:RAILWAY_MYSQL_HOST
$port = $env:RAILWAY_MYSQL_PORT
$user = $env:RAILWAY_MYSQL_USER
$password = $env:RAILWAY_MYSQL_PASSWORD
$database = $env:RAILWAY_MYSQL_DATABASE

if (-not $hostName) { $hostName = Read-Host "Railway MySQL public host (e.g. xxx.proxy.rlwy.net)" }
if (-not $port) { $port = Read-Host "Railway MySQL public port" }
if (-not $user) { $user = Read-Host "MYSQLUSER" }
if (-not $password) {
    $secure = Read-Host "MYSQLPASSWORD" -AsSecureString
    $bstr = [Runtime.InteropServices.Marshal]::SecureStringToBSTR($secure)
    $password = [Runtime.InteropServices.Marshal]::PtrToStringAuto($bstr)
}
if (-not $database) { $database = Read-Host "MYSQLDATABASE (often 'railway')" }

$backupDir = Join-Path $PSScriptRoot "..\backups"
New-Item -ItemType Directory -Force -Path $backupDir | Out-Null

$stamp = Get-Date -Format "yyyyMMdd_HHmmss"
$outFile = Join-Path $backupDir "railway_attendance_$stamp.sql"

Write-Host "Dumping $database from ${hostName}:${port} ..." -ForegroundColor Cyan

# Prefer defaults-extra-file so the password is not visible in process list as much.
$cnf = Join-Path $env:TEMP "railway_mysql_backup_$stamp.cnf"
@"
[client]
host=$hostName
port=$port
user=$user
password=$password
"@ | Set-Content -Path $cnf -Encoding ASCII

try {
    & $mysqldump --defaults-extra-file=$cnf `
        --single-transaction `
        --routines `
        --triggers `
        --hex-blob `
        --default-character-set=utf8mb4 `
        $database | Set-Content -Path $outFile -Encoding UTF8

    if ($LASTEXITCODE -ne 0) {
        throw "mysqldump failed with exit code $LASTEXITCODE"
    }

    $size = (Get-Item $outFile).Length
    Write-Host "Backup saved: $outFile ($([math]::Round($size/1KB,1)) KB)" -ForegroundColor Green
    Write-Host ""
    Write-Host "Restore later (example, into local MySQL):" -ForegroundColor Yellow
    Write-Host "  C:\xampp\mysql\bin\mysql.exe -u root attendance_system < `"$outFile`""
}
finally {
    Remove-Item $cnf -Force -ErrorAction SilentlyContinue
}
