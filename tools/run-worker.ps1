$ErrorActionPreference='Stop'
$projectRoot=[IO.Path]::GetFullPath((Join-Path $PSScriptRoot '..'))
$logPath=Join-Path $projectRoot 'storage/worker.log'
if((Test-Path -LiteralPath $logPath) -and (Get-Item -LiteralPath $logPath).Length -gt 5MB){Move-Item -LiteralPath $logPath -Destination ($logPath+'.previous') -Force}
$output=& 'C:\xampp\php\php.exe' (Join-Path $projectRoot 'bin/console.php') run 2>&1
$workerExit=$LASTEXITCODE
Add-Content -LiteralPath $logPath -Value (('['+(Get-Date -Format o)+'] ')+($output -join ' ')) -Encoding utf8
exit $workerExit
