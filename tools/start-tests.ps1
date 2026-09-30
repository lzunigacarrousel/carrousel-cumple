$ErrorActionPreference='Stop'
$projectRoot=[IO.Path]::GetFullPath((Join-Path $PSScriptRoot '..'))
$router=Join-Path $projectRoot 'tests/router.php'
$webRoot=Join-Path $projectRoot 'public'
if(Get-NetTCPConnection -State Listen -LocalPort 8787 -ErrorAction SilentlyContinue){throw 'Puerto 8787 ocupado.'}
$server=Start-Process -FilePath 'C:/xampp/php/php.exe' -ArgumentList @('-S','127.0.0.1:8787','-t',('"'+$webRoot+'"'),('"'+$router+'"')) -WorkingDirectory $projectRoot -WindowStyle Hidden -PassThru -RedirectStandardOutput (Join-Path $projectRoot 'storage/test-server.out.log') -RedirectStandardError (Join-Path $projectRoot 'storage/test-server.err.log')
@{pid=$server.Id;router=$router} | ConvertTo-Json | Set-Content -LiteralPath (Join-Path $projectRoot 'storage/test-server.json') -Encoding utf8
Write-Output 'Servidor de pruebas: http://127.0.0.1:8787/'
