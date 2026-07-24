# Restore a Railway MySQL .sql backup into a new Railway MySQL (or local XAMPP).
#
# Usage (new Railway project after trial / redeploy):
#   1) Create MySQL service + web service from this GitHub repo (see docs/BACKUP_AND_REDEPLOY.md)
#   2) Enable MySQL Public Networking and copy host/port/user/password/database
#   3) Run:
#
#    $env:RAILWAY_MYSQL_HOST="...."
#    $env:RAILWAY_MYSQL_PORT="...."
#    $env:RAILWAY_MYSQL_USER="...."
#    $env:RAILWAY_MYSQL_PASSWORD="...."
#    $env:RAILWAY_MYSQL_DATABASE="railway"
#    $env:BACKUP_SQL="C:\xampp\htdocs\attendance_system\backups\railway_attendance_YYYYMMDD_HHMMSS.sql"
#    .\scripts\restore-railway-mysql.ps1
#
# Local XAMPP restore example (no env needed for host):
#    $env:RESTORE_LOCAL="1"
#    $env:BACKUP_SQL="...\backups\railway_attendance_....sql"
#    $env:LOCAL_MYSQL_DATABASE="attendance_system"
#    .\scripts\restore-railway-mysql.ps1

$ErrorActionPreference = "Stop"

$mysql8 = "C:\Program Files\MySQL\MySQL Server 8.4\bin\mysql.exe"
$xamppMysql = "C:\xampp\mysql\bin\mysql.exe"
$mysql = if (Test-Path $mysql8) { $mysql8 } else { $xamppMysql }
if (-not (Test-Path $mysql)) {
    Write-Host "mysql client not found. Install MySQL 8 client or XAMPP." -ForegroundColor Red
    exit 1
}

$sqlFile = $env:BACKUP_SQL
if (-not $sqlFile) {
    $sqlFile = Read-Host "Path to .sql backup file"
}
if (-not (Test-Path $sqlFile)) {
    Write-Host "Backup file not found: $sqlFile" -ForegroundColor Red
    exit 1
}

$local = $env:RESTORE_LOCAL -eq "1"

if ($local) {
    $hostName = "127.0.0.1"
    $port = if ($env:LOCAL_MYSQL_PORT) { $env:LOCAL_MYSQL_PORT } else { "3306" }
    $user = if ($env:LOCAL_MYSQL_USER) { $env:LOCAL_MYSQL_USER } else { "root" }
    $password = if ($env:LOCAL_MYSQL_PASSWORD) { $env:LOCAL_MYSQL_PASSWORD } else { "" }
    $database = if ($env:LOCAL_MYSQL_DATABASE) { $env:LOCAL_MYSQL_DATABASE } else { "attendance_system" }
} else {
    $hostName = $env:RAILWAY_MYSQL_HOST
    $port = $env:RAILWAY_MYSQL_PORT
    $user = $env:RAILWAY_MYSQL_USER
    $password = $env:RAILWAY_MYSQL_PASSWORD
    $database = $env:RAILWAY_MYSQL_DATABASE

    if (-not $hostName) { $hostName = Read-Host "Railway MySQL public host" }
    if (-not $port) { $port = Read-Host "Railway MySQL public port" }
    if (-not $user) { $user = Read-Host "MYSQLUSER" }
    if (-not $password) {
        $secure = Read-Host "MYSQLPASSWORD" -AsSecureString
        $bstr = [Runtime.InteropServices.Marshal]::SecureStringToBSTR($secure)
        $password = [Runtime.InteropServices.Marshal]::PtrToStringAuto($bstr)
    }
    if (-not $database) { $database = Read-Host "MYSQLDATABASE (often 'railway')" }
}

Write-Host "Restoring $sqlFile into ${hostName}:${port}/$database ..." -ForegroundColor Cyan

$stamp = Get-Date -Format "yyyyMMdd_HHmmss"
$cnf = Join-Path $env:TEMP "railway_mysql_restore_$stamp.cnf"
$cnfBody = @"
[client]
host=$hostName
port=$port
user=$user
"@
if ($password -ne "") {
    $cnfBody += "`npassword=$password"
}
$cnfBody | Set-Content -Path $cnf -Encoding ASCII

try {
    # Ensure database exists (Railway usually already created it).
    & $mysql --defaults-extra-file=$cnf -e "CREATE DATABASE IF NOT EXISTS ``$database`` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
    if ($LASTEXITCODE -ne 0) {
        throw "Could not create/select database (exit $LASTEXITCODE)"
    }

    Get-Content -Path $sqlFile -Raw -Encoding UTF8 | & $mysql --defaults-extra-file=$cnf $database
    if ($LASTEXITCODE -ne 0) {
        throw "mysql restore failed with exit code $LASTEXITCODE"
    }

    Write-Host "Restore complete." -ForegroundColor Green
    Write-Host "Next: set APP_URL on the web service, redeploy, then open the site and log in." -ForegroundColor Yellow
}
finally {
    Remove-Item $cnf -Force -ErrorAction SilentlyContinue
}
