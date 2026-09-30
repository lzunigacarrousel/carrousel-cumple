#requires -Version 7.0
[CmdletBinding()]
param(
    [string]$Php = 'php',
    [switch]$Integration
)

$ErrorActionPreference = 'Stop'
$projectRoot = [IO.Path]::GetFullPath((Join-Path $PSScriptRoot '..'))
$phpCommand = (Get-Command $Php -CommandType Application -ErrorAction Stop | Select-Object -First 1).Source
$nodeCommand = (Get-Command node -CommandType Application -ErrorAction Stop | Select-Object -First 1).Source

function Invoke-Checked([string]$Executable, [string[]]$Arguments) {
    & $Executable @Arguments
    if ($LASTEXITCODE -ne 0) {
        throw "Falló $Executable (salida $LASTEXITCODE)."
    }
}

Push-Location $projectRoot
try {
    $phpFiles = @(Get-ChildItem -LiteralPath 'app', 'bin', 'public', 'tests' -Recurse -Filter '*.php' -File)
    $phpFiles += Get-Item -LiteralPath 'index.php', 'config/local.php.example', 'config/test.php.example'
    foreach ($file in $phpFiles) {
        Invoke-Checked $phpCommand @('-l', $file.FullName)
    }
    $jsFiles = @(Get-ChildItem -LiteralPath 'public' -Filter '*.js' -File)
    foreach ($file in $jsFiles) {
        Invoke-Checked $nodeCommand @('--check', $file.FullName)
    }
    Write-Host "Sintaxis válida: $($phpFiles.Count) archivos PHP y $($jsFiles.Count) JavaScript."
    if (-not $Integration) { return }

    Write-Host 'Integración: se reinician los fixtures de carrousel_cumple_test. No ejecutes otra suite sobre esa base al mismo tiempo.'
    $storage = Join-Path $projectRoot 'storage'
    [IO.Directory]::CreateDirectory($storage) | Out-Null
    $suiteLock = $null
    try {
        $suiteLock = [IO.File]::Open((Join-Path $storage 'test-suite.lock'), 'OpenOrCreate', 'ReadWrite', 'None')
        Invoke-Checked $phpCommand @('bin/setup-tests.php')
        # Installer checks run first; production.php then creates fixtures for the rest.
        foreach ($suite in @('install.php', 'production.php', 'production-edges.php', 'production-races.php', 'review-regressions.php', 'catalog.php')) {
            Invoke-Checked $phpCommand @("tests/$suite")
        }

        # Choose a temporary loopback port; never stop or reuse another PHP server.
        $listener = [Net.Sockets.TcpListener]::new([Net.IPAddress]::Loopback, 0)
        $listener.Start()
        $port = $listener.LocalEndpoint.Port
        $listener.Stop()
        $baseUrl = "http://127.0.0.1:$port"
        $startInfo = [Diagnostics.ProcessStartInfo]::new()
        $startInfo.FileName = $phpCommand
        $startInfo.WorkingDirectory = $projectRoot
        $startInfo.UseShellExecute = $false
        $startInfo.CreateNoWindow = $true
        $startInfo.RedirectStandardOutput = $true
        $startInfo.RedirectStandardError = $true
        foreach ($argument in @('-S', "127.0.0.1:$port", '-t', (Join-Path $projectRoot 'public'), (Join-Path $projectRoot 'tests/router.php'))) {
            $startInfo.ArgumentList.Add($argument)
        }
        $server = [Diagnostics.Process]::new()
        $server.StartInfo = $startInfo
        $serverStarted = $false
        $stdout = $null
        $stderr = $null
        try {
            $serverStarted = $server.Start()
            if (-not $serverStarted) { throw 'No se pudo iniciar el servidor de pruebas.' }
            $stdout = $server.StandardOutput.ReadToEndAsync()
            $stderr = $server.StandardError.ReadToEndAsync()
            $ready = $false
            for ($attempt = 0; $attempt -lt 40; $attempt++) {
                if ($server.HasExited) { throw 'El servidor de pruebas terminó antes de estar listo.' }
                try {
                    $probe = Invoke-WebRequest -Uri "$baseUrl/api.php?action=bootstrap" -TimeoutSec 2
                    $ready = $probe.StatusCode -eq 200
                } catch { $ready = $false }
                if ($ready) { break }
                Start-Sleep -Milliseconds 250
            }
            if (-not $ready) { throw 'El servidor de pruebas no respondió.' }
            & (Join-Path $projectRoot 'tests/http-production.ps1') -BaseUrl $baseUrl
        } finally {
            if ($serverStarted -and -not $server.HasExited) {
                $server.Kill()
                $server.WaitForExit()
            }
            if ($null -ne $stdout) {
                [IO.File]::WriteAllText((Join-Path $storage 'check-http.out.log'), $stdout.GetAwaiter().GetResult())
                [IO.File]::WriteAllText((Join-Path $storage 'check-http.err.log'), $stderr.GetAwaiter().GetResult())
            }
            $server.Dispose()
        }
    } finally {
        if ($null -ne $suiteLock) { $suiteLock.Dispose() }
    }
    Write-Host 'Todas las comprobaciones de sintaxis, dominio, concurrencia, catálogos y HTTP terminaron correctamente.'
} finally {
    Pop-Location
}
