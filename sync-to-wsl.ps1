<#
.SYNOPSIS
    Syncs project files from this Windows workspace to /var/www/html in WSL (Ubuntu).
#>

$Source = $PSScriptRoot
$Destination = "\\wsl.localhost\Ubuntu\var\www\html"

if (-not (Test-Path $Destination)) {
    Write-Error "WSL path not reachable: $Destination. Make sure Ubuntu WSL is running (e.g. 'wsl -d Ubuntu true')."
    exit 1
}

Write-Host "Syncing '$Source' -> '$Destination'..." -ForegroundColor Cyan

# Exclude .git and this helper script; robocopy exit codes 0-7 indicate success.
robocopy $Source $Destination /E /XD .git /XF sync-to-wsl.ps1 /NFL /NDL /NJH /NJS

if ($LASTEXITCODE -lt 8) {
    Write-Host "Sync complete." -ForegroundColor Green
    exit 0
} else {
    Write-Error "Sync failed with robocopy exit code $LASTEXITCODE."
    exit $LASTEXITCODE
}
