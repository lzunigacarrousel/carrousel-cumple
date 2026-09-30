param([int]$Port = 8786, [switch]$Open)
$ErrorActionPreference = 'Stop'
$projectRoot = [System.IO.Path]::GetFullPath((Join-Path $PSScriptRoot '..'))
$storagePath = Join-Path $projectRoot 'storage'
New-Item -ItemType Directory -Force -Path $storagePath | Out-Null
$recordPath = Join-Path $storagePath "server-$Port.json"
$publicPath = Join-Path $projectRoot 'public'
$routerPath = Join-Path $publicPath 'router.php'
$url = "http://127.0.0.1:$Port/"
$phpPath = 'C:\xampp\php\php.exe'
if (-not (Test-Path -LiteralPath $phpPath)) {
    $phpCommand = Get-Command php -ErrorAction SilentlyContinue
    if (-not $phpCommand) { throw 'No se encontró PHP. Instala PHP 8.2 con PDO MySQL o XAMPP.' }
    $phpPath = $phpCommand.Source
}
if (Test-Path -LiteralPath $recordPath) {
    $record = Get-Content -LiteralPath $recordPath -Raw | ConvertFrom-Json
    $running = Get-CimInstance Win32_Process -Filter "ProcessId=$([int]$record.pid)" -ErrorAction SilentlyContinue
    if ($running -and $running.CommandLine -and $running.CommandLine.Contains($routerPath)) {
        Write-Output "Carrousel Cumple ya está disponible: $url"
        if ($Open) { Start-Process $url }
        exit 0
    }
}
$occupied = Get-NetTCPConnection -State Listen -LocalPort $Port -ErrorAction SilentlyContinue
if ($occupied) { throw "El puerto $Port está ocupado por otro proceso. Usa -Port con un puerto libre." }
$configPath = if ($env:CUMPLE_CONFIG) { $env:CUMPLE_CONFIG } else { Join-Path $projectRoot 'config/local.php' }
if (-not (Test-Path -LiteralPath $configPath)) { throw 'Falta la configuración MariaDB. Sigue INSTALACION.md antes de iniciar.' }
$serverArgs = @('-S', "127.0.0.1:$Port", '-t', ('"' + $publicPath + '"'), ('"' + $routerPath + '"'))
$serverProcess = Start-Process -FilePath $phpPath -ArgumentList $serverArgs -WorkingDirectory $projectRoot -WindowStyle Hidden -PassThru -RedirectStandardOutput (Join-Path $storagePath "server-$Port.out.log") -RedirectStandardError (Join-Path $storagePath "server-$Port.err.log")
@{pid=$serverProcess.Id;port=$Port;url=$url;root=$projectRoot;started=(Get-Date -Format o)} | ConvertTo-Json | Set-Content -LiteralPath $recordPath -Encoding UTF8
$ready = $false
for ($attempt = 0; $attempt -lt 20; $attempt++) {
    try {
        $reply = Invoke-WebRequest -Uri $url -UseBasicParsing -TimeoutSec 2
        if ($reply.StatusCode -eq 200) { $ready = $true; break }
    } catch { Start-Sleep -Milliseconds 200 }
}
if (-not $ready) { throw "No se pudo iniciar. Revisa storage/server-$Port.err.log." }
Write-Output "Carrousel Cumple disponible: $url"
Write-Output 'Servidor de desarrollo conectado a la configuración MariaDB de este entorno.'
if ($Open) { Start-Process $url }
