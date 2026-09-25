# CampusFlow 4-Tier Test Runner Wrapper (PowerShell)
param (
    [string]$Tier = "",
    [string]$Feature = "",
    [switch]$WithServer = $false,
    [switch]$Help = $false
)

$ErrorActionPreference = "Stop"

if ($Help) {
    Write-Host "Usage: .\tests\run_tests.ps1 [-Tier <1|2|3|4>] [-Feature <F1..F9>] [-WithServer] [-Help]" -ForegroundColor Cyan
    exit 0
}

# Resolve PHP executable
$php = "php"
if (-not (Get-Command php -ErrorAction SilentlyContinue)) {
    if (Test-Path "C:\xampp\php\php.exe") {
        $php = "C:\xampp\php\php.exe"
    } else {
        Write-Error "PHP executable not found in PATH or at C:\xampp\php\php.exe"
        exit 1
    }
}

$argsList = @("-d", "extension=pdo_pgsql", "tests\run_all.php")

if ($Tier -ne "") {
    $argsList += "--tier=$Tier"
}
if ($Feature -ne "") {
    $argsList += "--feature=$Feature"
}
if ($WithServer) {
    $argsList += "--with-server"
}

Write-Host "Running CampusFlow E2E Test Suite via $php..." -ForegroundColor Cyan
& $php $argsList
exit $LASTEXITCODE
