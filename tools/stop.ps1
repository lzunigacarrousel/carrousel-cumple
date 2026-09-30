param([int]$Port = 8786)
$ErrorActionPreference = 'Stop'
$projectRoot = [System.IO.Path]::GetFullPath((Join-Path $PSScriptRoot '..'))
$recordPath = Join-Path $projectRoot "storage\server-$Port.json"
if (-not (Test-Path -LiteralPath $recordPath)) { Write-Output 'No hay servidor registrado.'; exit 0 }
$record = Get-Content -LiteralPath $recordPath -Raw | ConvertFrom-Json
$running = Get-CimInstance Win32_Process -Filter "ProcessId=$([int]$record.pid)" -ErrorAction SilentlyContinue
$routerPath = Join-Path $projectRoot 'public\router.php'
if ($running -and $running.CommandLine -and $running.CommandLine.Contains($routerPath)) {
    Stop-Process -Id ([int]$record.pid)
    Write-Output 'Servidor local de desarrollo detenido. Tus registros siguen guardados.'
} elseif ($running) {
    throw 'El proceso registrado ya no corresponde a este proyecto; no se detuvo.'
}
Remove-Item -LiteralPath $recordPath
