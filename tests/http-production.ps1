param([string]$BaseUrl='http://127.0.0.1:8787')
$ErrorActionPreference='Stop'
$checks=0
function Check($ok,$message){if(-not $ok){throw "FAIL: $message"};$script:checks++;Write-Host "PASS: $message"}
function Call([string]$action,$body=$null,[bool]$csrf=$true){
    $script:session.Headers.Remove('X-CSRF-Token')
    $p=@{Uri="$BaseUrl/api.php?action=$action";WebSession=$script:session;SkipHttpErrorCheck=$true}
    if($null -ne $body){$p.Method='POST';$p.ContentType='application/json';$p.Body=ConvertTo-Json -InputObject $body -Depth 5 -Compress;if($csrf){$p.Headers=@{'X-CSRF-Token'=$script:token}}}
    $r=Invoke-WebRequest @p
    return @{status=[int]$r.StatusCode;json=($r.Content|ConvertFrom-Json)}
}
function Login($email){
    $script:session=New-Object Microsoft.PowerShell.Commands.WebRequestSession
    $script:token=(Call 'bootstrap').json.data.csrf
    $request=Call 'otp-request' @{email=$email}
    Check ($request.status -eq 200) 'Solicita OTP por correo'
    $outbox=Get-Content -LiteralPath (Join-Path $PSScriptRoot '../storage/test-outbox.json') -Raw | ConvertFrom-Json
    Check ($outbox.to -eq $email) 'OTP dirigido a la cuenta solicitada'
    $code=[regex]::Match($outbox.text,'Código: (\d{6})').Groups[1].Value
    $verified=Call 'otp-verify' @{challenge=$request.json.data.challenge;code=$code}
    Check ($verified.status -eq 200) 'Acceso real por OTP'
    $script:token=$verified.json.data.csrf
}
$session=New-Object Microsoft.PowerShell.Commands.WebRequestSession
$boot=Call 'bootstrap';$token=$boot.json.data.csrf
Check ($boot.status -eq 200 -and -not $boot.json.data.user -and -not $boot.json.data.PSObject.Properties['demo_accounts']) 'Bootstrap sin usuarios de demostración'
Check ((Call 'dashboard').status -eq 401) 'Panel privado'
Check ((Call 'otp-request' @{email='admin@example.test'} $false).status -eq 403) 'OTP sin CSRF rechazado'
Check ((Call 'demo-login' @{account_id=1}).status -eq 404) 'No existe acceso demo'
Login 'admin@example.test'
Check ((Call 'users').status -eq 200 -and (Call 'roles').status -eq 200 -and (Call 'centers').status -eq 200) 'Catálogos de administración conectados'
$campaign=Call 'campaign' @{title='Campaña HTTP QA';benefit_type='product';benefit_label='Obsequio QA';quantity='';terms='Condiciones QA';email_subject='Tu cumpleaños QA';email_intro='Mensaje QA';status='active'}
Check ($campaign.status -eq 200) 'Admin configura regalo variable'
Login 'a@example.test'
Check ((Call 'users').status -eq 403 -and (Call 'campaigns').status -eq 403) 'Cuenta de parque sin administración'
$today=Get-Date
$payload=@{name=('Cliente HTTP '+[Guid]::NewGuid().ToString('N').Substring(0,6));day=$today.Day;month=$today.Month;email='customer-http@example.test';phone='55551234';terms_accepted=$true;request_id=[Guid]::NewGuid().ToString()}
$registered=Call 'customer' $payload
Check ($registered.status -eq 200 -and $registered.json.data.customer.id -and -not $registered.json.data.PSObject.Properties['coupon']) 'Alta devuelve cliente, no cupón'
Check ((Call 'customer' ($payload+@{center_id=2})).status -eq 403) 'Centro no modificable por petición'
Login 'admin@example.test'
$issued=Call 'run-issuance' @{}
Check ($issued.status -eq 200 -and $issued.json.data.issued -ge 1) 'Proceso emite los regalos pendientes'
$list=(Call ('coupons&q='+[uri]::EscapeDataString($payload.name))).json.data
$coupon=@($list)[0]
Check ([bool]$coupon.code) 'Cupón anual visible en lista'
Check ((Call 'mail').status -eq 200 -and (Call 'audit').status -eq 200) 'Envíos y bitácora disponibles'
Login 'b@example.test'
Check ((Call ('lookup&code='+$coupon.code)).status -eq 200) 'Consulta global desde otro centro'
$used=Call 'redeem' @{code=$coupon.code}
Check ($used.status -eq 200 -and $used.json.data.redeemed_center_name -eq 'Centro B') 'Canje registrado en centro de la cuenta'
Login 'a@example.test'
Check ((Call 'redeem' @{code=$coupon.code}).status -eq 422) 'Segundo canje rechazado en centro de origen'
Check ((Call 'logout' @{}).status -eq 200) 'Cierre de sesión'
Check ((Call 'customers').status -eq 401) 'Sesión cerrada ya no consulta clientes'
Write-Output "$checks comprobaciones HTTP correctas."
