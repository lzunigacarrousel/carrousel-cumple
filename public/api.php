<?php
declare(strict_types=1);
require_once dirname(__DIR__).'/app/Web.php';
try{
    require dirname(__DIR__).'/app/bootstrap.php';
    $action=Web::query('action',40);$method=$_SERVER['REQUEST_METHOD']??'GET';
    $reads=['bootstrap','dashboard','customers','coupons','lookup','coupon','campaigns','mail','users','roles','centers','audit'];
    $writes=['otp-request','otp-verify','logout','customer','customer-status','redeem','void','campaign','mail-retry','run-issuance','user','role','center'];
    if(!in_array($action,array_merge($reads,$writes),true))Web::fail('Acción no encontrada.',404);
    if(($method!=='GET'&&in_array($action,$reads,true))||($method!=='POST'&&in_array($action,$writes,true)))Web::fail('Método no permitido.',405);
    $p=$method==='POST'?Web::input():[];$ip=$_SERVER['REMOTE_ADDR']??'';$identity->throttle('api:'.$ip,600,60);
    if($action==='bootstrap')Web::success(['csrf'=>$_SESSION['csrf'],'user'=>Web::currentUser($identity),'today'=>date('Y-m-d'),'app_url'=>$config['app_url'],'mail_ready'=>$mailer->health()['ready']]);
    if($action==='otp-request')Web::success($identity->requestOtp((string)($p['email']??''),$_SESSION['otp_binding'],$ip));
    if($action==='otp-verify'){
        $u=$identity->verifyOtp((string)($p['challenge']??''),(string)($p['code']??''),$_SESSION['otp_binding'],$ip);
        session_regenerate_id(true);$_SESSION['user_id']=$u['id'];$_SESSION['auth_version']=$u['auth_version'];$_SESSION['csrf']=bin2hex(random_bytes(32));unset($_SESSION['otp_binding']);Web::success(['user'=>$u,'csrf'=>$_SESSION['csrf']]);
    }
    if($action==='logout'){$_SESSION=['csrf'=>bin2hex(random_bytes(32))];session_regenerate_id(true);Web::success(['csrf'=>$_SESSION['csrf']]);}
    if($action==='coupon'){$identity->throttle('gift:'.$ip,120,60);Web::success($program->publicCoupon(Web::query('token',64)));}
    $u=Web::user($identity);
    switch($action){
        case 'dashboard':Web::success($program->dashboard($u));
        case 'customers':Web::success($program->customers($u,Web::query('q')));
        case 'customer':Web::success($program->register($u,$p));
        case 'customer-status':Web::success($program->customerStatus($u,(int)($p['id']??0),!empty($p['active'])));
        case 'coupons':Web::success($program->coupons($u,['q'=>Web::query('q'),'status'=>Web::query('status',20)]));
        case 'lookup':Web::success($program->lookup($u,Web::query('code',100)));
        case 'redeem':Web::success($program->redeem($u,(string)($p['code']??''),$p));
        case 'void':Web::success($program->void($u,(string)($p['code']??''),(string)($p['reason']??'')));
        case 'campaigns':$identity->require($u,'campaigns.manage');Web::success($program->campaigns());
        case 'campaign':Web::success($program->saveCampaign($u,$p));
        case 'mail':$identity->require($u,'mail.manage');Web::success($delivery->listing());
        case 'mail-retry':$identity->require($u,'mail.manage');Web::success($delivery->retry($u,(int)($p['id']??0)));
        case 'run-issuance':$identity->require($u,'mail.manage');$issued=$program->issue();Database::audit($db,$u['id'],'MANUAL_ISSUANCE','campaign',null,['issued'=>$issued]);Web::success(['issued'=>$issued]);
        case 'users':$identity->require($u,'users.manage');Web::success($identity->users());
        case 'user':Web::success($identity->saveUser($u,$p));
        case 'roles':if(!in_array('users.manage',$u['permissions'],true))$identity->require($u,'roles.manage');Web::success($identity->roles());
        case 'role':Web::success($identity->saveRole($u,$p));
        case 'centers':if(!in_array('users.manage',$u['permissions'],true))$identity->require($u,'centers.manage');Web::success($identity->centers());
        case 'center':Web::success($identity->saveCenter($u,$p));
        case 'audit':$identity->require($u,'audit.read');Web::success($db->query('SELECT a.*,u.name actor_name FROM audit a LEFT JOIN users u ON u.id=a.actor_id ORDER BY a.id DESC LIMIT 300')->fetchAll());
    }
}catch(DomainException $e){$code=(int)$e->getCode();Web::fail($e->getMessage(),$code>=400&&$code<=599?$code:422);}
catch(PDOException $e){error_log('Database failure '.$e->getCode().' at '.basename($e->getFile()).':'.$e->getLine());Web::fail($e->getCode()==='23000'?'Ya existe un registro con esos datos. Revisa el correo o código.':'No se pudo completar la operación en la base central.',500);}
catch(Throwable $e){error_log('Application failure '.get_class($e).' at '.basename($e->getFile()).':'.$e->getLine());Web::fail('No se pudo completar la operación. Revisa la instalación y la configuración.',503);}
