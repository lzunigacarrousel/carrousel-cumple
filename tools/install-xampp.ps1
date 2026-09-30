param([string]$Destination='C:\xampp\htdocs\CarrouselCumple')
$ErrorActionPreference='Stop'
$sourceRoot=[IO.Path]::GetFullPath((Join-Path $PSScriptRoot '..'))
$targetRoot=[IO.Path]::GetFullPath($Destination).TrimEnd('\')
$htdocsRoot=[IO.Path]::GetFullPath('C:\xampp\htdocs').TrimEnd('\')
if(-not $targetRoot.StartsWith($htdocsRoot+'\',[StringComparison]::OrdinalIgnoreCase) -or $targetRoot -eq $htdocsRoot){throw 'Destino fuera de una subcarpeta de htdocs.'}
if((Test-Path -LiteralPath $targetRoot) -and -not (Test-Path -LiteralPath (Join-Path $targetRoot '.carrousel-cumple'))){throw 'La carpeta ya existe y no pertenece a esta instalación. No se sobrescribe.'}
$targetConfig=Join-Path $targetRoot 'config/local.php'
if(-not(Test-Path -LiteralPath (Join-Path $sourceRoot 'vendor/phpmailer/phpmailer/src/PHPMailer.php'))){throw 'Faltan dependencias. Ejecuta composer install --no-dev en el proyecto antes de instalar.'}
if(-not(Test-Path -LiteralPath $targetConfig) -and -not(Test-Path -LiteralPath (Join-Path $sourceRoot 'config/local.php'))){throw 'Copia config/local.php.example a config/local.php y configura tu entorno según INSTALACION.md antes de instalar.'}
New-Item -ItemType Directory -Force -Path $targetRoot | Out-Null
foreach($folder in @('app','bin','config','database','public','vendor','storage','storage/sessions','tools','docs')){New-Item -ItemType Directory -Force -Path (Join-Path $targetRoot $folder)|Out-Null}
foreach($file in @('.htaccess','index.php','composer.json','composer.lock','INSTALACION.md','README.md','THIRD_PARTY_NOTICES.md')){if(Test-Path -LiteralPath (Join-Path $sourceRoot $file)){Copy-Item -LiteralPath (Join-Path $sourceRoot $file) -Destination (Join-Path $targetRoot $file) -Force}}
foreach($class in @('Database','Web','Identity','Program','Delivery','Mailer','TrackedSMTP','ImportCatalog','bootstrap')){Copy-Item -LiteralPath (Join-Path $sourceRoot "app/$class.php") -Destination (Join-Path $targetRoot "app/$class.php") -Force}
foreach($folder in @('public','database','vendor')){Get-ChildItem -LiteralPath (Join-Path $sourceRoot $folder) -Force | Copy-Item -Destination (Join-Path $targetRoot $folder) -Recurse -Force}
foreach($file in @('bin/console.php','bin/create-admin.php','bin/check-smtp.php','config/local.php.example','tools/schedule.ps1','tools/run-worker.ps1')){if(Test-Path -LiteralPath (Join-Path $sourceRoot $file)){Copy-Item -LiteralPath (Join-Path $sourceRoot $file) -Destination (Join-Path $targetRoot $file) -Force}}
if(-not(Test-Path -LiteralPath $targetConfig)){Copy-Item -LiteralPath (Join-Path $sourceRoot 'config/local.php') -Destination $targetConfig}
Set-Content -LiteralPath (Join-Path $targetRoot '.carrousel-cumple') -Value 'Carrousel Cumple 1' -Encoding ascii
Write-Output "Aplicación instalada en $targetRoot"
Write-Output 'Configura/migra la base y crea el administrador según INSTALACION.md. La copia no modifica datos ni activa el envío.'
Write-Output ('Abrir http://localhost/' + [IO.Path]::GetFileName($targetRoot) + '/')
