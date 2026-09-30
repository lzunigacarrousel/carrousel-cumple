param([string]$ProjectPath='C:\xampp\htdocs\CarrouselCumple',[switch]$CurrentUser)
$ErrorActionPreference='Stop'
$projectRoot=[IO.Path]::GetFullPath($ProjectPath)
$worker=Join-Path $projectRoot 'tools/run-worker.ps1'
if(-not(Test-Path -LiteralPath (Join-Path $projectRoot '.carrousel-cumple')) -or -not(Test-Path -LiteralPath $worker)){throw 'Instala Carrousel Cumple antes de programar el envío.'}
$name='CarrouselCumple-Campanas'
$action=New-ScheduledTaskAction -Execute 'powershell.exe' -Argument ('-NoProfile -NonInteractive -WindowStyle Hidden -ExecutionPolicy Bypass -File "'+$worker+'"') -WorkingDirectory $projectRoot
$trigger=New-ScheduledTaskTrigger -Once -At (Get-Date).AddMinutes(1) -RepetitionInterval (New-TimeSpan -Minutes 5)
$settings=New-ScheduledTaskSettingsSet -StartWhenAvailable -MultipleInstances IgnoreNew -ExecutionTimeLimit (New-TimeSpan -Minutes 4) -AllowStartIfOnBatteries -DontStopIfGoingOnBatteries
if($CurrentUser){$principal=New-ScheduledTaskPrincipal -UserId ([Security.Principal.WindowsIdentity]::GetCurrent().Name) -LogonType Interactive -RunLevel Limited}
else {$principal=New-ScheduledTaskPrincipal -UserId 'SYSTEM' -LogonType ServiceAccount -RunLevel Highest}
$existing=Get-ScheduledTask -TaskName $name -ErrorAction SilentlyContinue
if($existing -and -not(($existing.Actions.Arguments -join ' ').Contains($worker))){throw 'Existe una tarea con el mismo nombre para otra ubicación.'}
Register-ScheduledTask -TaskName $name -Action $action -Trigger $trigger -Settings $settings -Principal $principal -Description 'Emite regalos de cumpleaños y procesa la cola central cada cinco minutos.' -Force | Select-Object TaskName,State
