<?php
declare(strict_types=1);

// Fixtures belong only to this run; do not reset the shared browser-test database.
$root=dirname(__DIR__);
foreach(['Database','Mailer','Identity','Program','Web'] as $class)require_once "$root/app/$class.php";
$cfg=require "$root/config/test.php";
if($cfg['db_name']!=='carrousel_cumple_test')throw new RuntimeException('Solo base de pruebas.');
date_default_timezone_set($cfg['timezone']);
$db=Database::connect($cfg);$messages=[];
$mailer=new Mailer($cfg,function(array $message)use(&$messages){$messages[]=$message;});
$identity=new Identity($db,$cfg,$mailer);
$program=new Program($db,$identity,new DateTimeImmutable('2026-09-22 12:00:00'));
$prefix='rr_'.bin2hex(random_bytes(5));$count=0;$failures=[];$userIds=[];$roleIds=[];$centerIds=[];$customerIds=[];$campaignIds=[];
function check(bool $ok,string $label):void{global $count,$failures;$count++;echo ($ok?'PASS ':'FAIL ').$label."\n";if(!$ok)$failures[]=$label;}
function rejects(callable $fn,string $label):void{try{$fn();check(false,$label);}catch(DomainException){check(true,$label);}}
function otp(Identity $identity,string $email,string $session):array{global $messages,$prefix;$request=$identity->requestOtp($email,$session,$prefix);preg_match('/Código: (\d{6})/',end($messages)['text'],$match);return [$request['challenge'],$match[1]];}
function deleteIds(PDO $db,string $table,string $column,array $ids):void{if($ids)$db->prepare("DELETE FROM $table WHERE $column IN (".implode(',',array_fill(0,count($ids),'?')).')')->execute($ids);}
function fixtureCustomer(PDO $db,string $prefix,int $number,int $center,int $user):int{
    $db->prepare('INSERT INTO customers(identity_key,name,day,month,email,phone,terms_accepted_at,terms_version,registered_by,center_id,created_at,updated_at) VALUES(?,?,22,9,?,?,NOW(),?,?,?,NOW(),NOW())')
        ->execute([hash('sha256',"$prefix-$number"),"$prefix Cliente $number","$prefix.$number@example.test",'5550000','regression',$user,$center]);
    return (int)$db->lastInsertId();
}
function fixtureCoupon(PDO $db,string $prefix,int $number,int $customer,int $campaign,string $status,int $user,int $center):int{
    $from=$status==='upcoming'?'2026-09-23':'2026-09-07';$until=$status==='expired'?'2026-09-21':'2026-10-07';
    $used=$status==='redeemed'||$status==='void';$void=$status==='void';
    $db->prepare('INSERT INTO coupons(customer_id,campaign_id,birthday_year,code,token,benefit_type,benefit_label,quantity,terms,email_subject,email_intro,valid_from,valid_until,created_at,redeemed_at,redeemed_by,redeemed_center_id,voided_at,voided_by,void_reason) VALUES(?,?,2026,?,?,\'tokens\',\'Regalo de cumpleaños\',50,\'Condiciones\',\'Tu regalo\',\'Feliz cumpleaños\',?,?,NOW(),?,?,?,?,?,?)')
        ->execute([$customer,$campaign,"$prefix-$number",hash('sha256',"token-$prefix-$number"),$from,$until,$used?'2026-09-22 10:00:00':null,$used?$user:null,$used?$center:null,$void?'2026-09-22 11:00:00':null,$void?$user:null,$void?'Anulado QA':null]);
    return (int)$db->lastInsertId();
}

try{
    $adminRole=(int)$db->query("SELECT id FROM roles WHERE code='ADMINISTRADOR'")->fetchColumn();
    if(!$adminRole)throw new RuntimeException('Ejecuta primero el esquema y los fixtures iniciales de pruebas.');
    $permissions=['customers.read','customers.write','coupons.read','coupons.redeem'];
    foreach(['A','B'] as $suffix){
        $db->prepare('INSERT INTO roles(code,name,permissions_json) VALUES(?,?,?)')->execute([strtoupper($prefix).'_'.$suffix,"$prefix Rol $suffix",json_encode($permissions)]);$roleIds[]=(int)$db->lastInsertId();
        $db->prepare('INSERT INTO cost_centers(code,name,park_name) VALUES(?,?,?)')->execute(["$prefix-$suffix","$prefix Centro $suffix","$prefix Parque $suffix"]);$centerIds[]=(int)$db->lastInsertId();
    }
    foreach([['Admin',$adminRole,null],['A',$roleIds[0],$centerIds[0]],['B',$roleIds[1],$centerIds[1]]] as [$name,$role,$center]){
        $db->prepare('INSERT INTO users(name,email,role_id,center_id) VALUES(?,?,?,?)')->execute(["$prefix $name",strtolower("$prefix.$name@example.test"),$role,$center]);$userIds[]=(int)$db->lastInsertId();
    }
    [$admin,$a,$b]=array_map(fn($id)=>$identity->user($id),$userIds);
    $input=['name'=>"$prefix Cliente privado",'day'=>22,'month'=>9,'email'=>"$prefix.cliente@example.test",'phone'=>'555-privado','guardian'=>'Responsable privado','is_minor'=>true,'terms_accepted'=>true,'request_id'=>"$prefix-original"];
    $own=$program->register($a,$input);$customerIds[]=(int)$own['customer']['id'];
    $foreignInput=array_replace($input,['phone'=>'555-inventado','guardian'=>'Responsable inventado','request_id'=>"$prefix-foreign"]);
    $foreign=$program->register($b,$foreignInput);$repeat=$program->register($b,$foreignInput);
    check(array_keys($foreign['customer'])===['id','name'],'Alta duplicada ajena devuelve solo reconocimiento');
    check(array_keys($repeat['customer'])===['id','name'],'Repetición idempotente ajena no expone datos privados');

    $saveA=['id'=>$a['id'],'name'=>$a['name'],'email'=>$a['email'],'role_id'=>$a['role_id'],'center_id'=>$a['center_id'],'active'=>true,'scope_center_ids'=>[]];
    [$challenge,$code]=otp($identity,$a['email'],'before-email');
    $saveA['email']="$prefix.nuevo@example.test";$identity->saveUser($admin,$saveA);
    rejects(fn()=>$identity->verifyOtp($challenge,$code,'before-email',$prefix),'Cambio de correo revoca OTP anterior');
    $_SESSION=['user_id'=>$a['id'],'auth_version'=>$a['auth_version']];
    check(Web::currentUser($identity)===null,'Cambio de cuenta revoca sesión con auth_version anterior');
    $a=$identity->user($a['id']);
    check(array_keys($program->register($a,$input)['customer'])!==['id','name'],'Repetición propia conserva datos autorizados');
    [$challenge,$code]=otp($identity,$a['email'],'before-center');
    $saveA['center_id']=$centerIds[1];$identity->saveUser($admin,$saveA);
    rejects(fn()=>$identity->verifyOtp($challenge,$code,'before-center',$prefix),'Cambio de centro revoca OTP anterior');
    $a=$identity->user($a['id']);
    check(array_keys($program->register($a,$input)['customer'])===['id','name'],'Repetición tras reasignar centro no expone cliente anterior');
    [$challenge,$code]=otp($identity,$a['email'],'before-role');
    $saveA['role_id']=$roleIds[1];$identity->saveUser($admin,$saveA);
    rejects(fn()=>$identity->verifyOtp($challenge,$code,'before-role',$prefix),'Reasignación de rol revoca OTP anterior');
    [$challenge,$code]=otp($identity,$a['email'],'before-matrix');
    $a=$identity->user($a['id']);
    $identity->saveRole($admin,['id'=>$roleIds[1],'code'=>strtoupper($prefix).'_B','name'=>"$prefix Rol actualizado",'permissions'=>$permissions]);
    rejects(fn()=>$identity->verifyOtp($challenge,$code,'before-matrix',$prefix),'Edición de permisos revoca OTP del rol');
    $_SESSION=['user_id'=>$a['id'],'auth_version'=>$a['auth_version']];
    check(Web::currentUser($identity)===null,'Edición del rol revoca sesiones anteriores');
    $identity->saveRole($admin,['id'=>$roleIds[1],'code'=>strtoupper($prefix).'_B','name'=>"$prefix Rol actualizado",'permissions'=>['customers.write','coupons.redeem']]);
    $effective=$identity->user($a['id'])['permissions'];
    check(in_array('customers.read',$effective,true)&&in_array('coupons.read',$effective,true),'Permisos operativos incorporan sus consultas necesarias');
    [$challenge,$code]=otp($identity,$a['email'],'new-identity');
    $current=$identity->verifyOtp($challenge,$code,'new-identity',$prefix);
    check($current['id']===$a['id']&&$current['auth_version']===$identity->user($a['id'])['auth_version'],'OTP nuevo autentica con versión actual');
    $saveA['center_id']=$centerIds[0];$saveA['role_id']=$roleIds[0];$identity->saveUser($admin,$saveA);$a=$identity->user($a['id']);$b=$identity->user($b['id']);

    $campaignInput=['title'=>"$prefix Campaña",'benefit_type'=>'tokens','benefit_label'=>'Regalo de cumpleaños','quantity'=>50,'terms'=>'Condiciones','email_subject'=>'Tu regalo','email_intro'=>'Feliz cumpleaños','status'=>'paused'];
    $campaign=$program->saveCampaign($admin,$campaignInput);$campaignIds[]=(int)$campaign['id'];
    $states=['redeemed','expired','upcoming','void'];$targetIds=[];
    $db->beginTransaction();
    for($i=0;$i<505;$i++){
        $customer=fixtureCustomer($db,$prefix,$i,$centerIds[0],$a['id']);$customerIds[]=$customer;
        $id=fixtureCoupon($db,$prefix,$i,$customer,(int)$campaign['id'],$states[$i]??'available',$a['id'],$centerIds[0]);
        if(isset($states[$i]))$targetIds[$states[$i]]=$id;
    }
    $foreignCustomer=fixtureCustomer($db,$prefix,999,$centerIds[1],$b['id']);$customerIds[]=$foreignCustomer;
    $foreignCoupon=fixtureCoupon($db,$prefix,999,$foreignCustomer,(int)$campaign['id'],'available',$b['id'],$centerIds[1]);
    $db->commit();
    $program->saveCampaign($admin,array_replace($campaignInput,['id'=>$campaign['id'],'quantity'=>80]));
    $mailer->sendCoupon($program->coupon($foreignCoupon),"$prefix.regalo@example.test","<$prefix@carrousel-apps.com>");$message=end($messages);
    check(str_contains($message['text'],'50 tokens')&&!str_contains($message['text'],'80 tokens'),'Correo texto comunica cantidad emitida conservada');
    check(str_contains($message['html'],'50 tokens')&&!str_contains($message['html'],'80 tokens'),'Correo HTML comunica cantidad emitida conservada');
    $stats=$program->dashboard($a)['stats'];
    check($stats['customers']===506,'Inicio cuenta más de 500 clientes dentro del alcance');
    check($stats['available']===501&&$stats['redeemed']===1,'Inicio agrega todos los estados sin truncar ni incluir otro centro');
    $otherStats=$program->dashboard($b)['stats'];
    check($otherStats['customers']===1&&$otherStats['available']===1&&$otherStats['redeemed']===0,'Totales del otro centro conservan aislamiento');
    foreach($targetIds as $status=>$id){$rows=$program->coupons($a,['status'=>$status]);check(count($rows)===1&&(int)$rows[0]['id']===$id,"Filtro $status encuentra registro anterior a 500 de otro estado");}
    check(count($program->coupons($a,['status'=>'available']))===500,'Lista mantiene límite después de filtrar');
    check(count($program->coupons($a,['status'=>'available','q'=>"$prefix-4"]))>0,'Filtro de estado conserva búsqueda por código');
    rejects(fn()=>$program->coupons($a,['status'=>'unknown']),'Estado de filtro inválido se rechaza');
}finally{
    if($db->inTransaction())$db->rollBack();
    deleteIds($db,'mail_queue','coupon_id',$db->query('SELECT id FROM coupons WHERE campaign_id IN ('.implode(',',$campaignIds?:[0]).')')->fetchAll(PDO::FETCH_COLUMN));
    deleteIds($db,'coupons','campaign_id',$campaignIds);deleteIds($db,'registration_requests','customer_id',$customerIds);deleteIds($db,'customers','id',$customerIds);deleteIds($db,'campaigns','id',$campaignIds);
    deleteIds($db,'audit','actor_id',$userIds);deleteIds($db,'otp_codes','user_id',$userIds);deleteIds($db,'user_centers','user_id',$userIds);deleteIds($db,'users','id',$userIds);deleteIds($db,'roles','id',$roleIds);deleteIds($db,'cost_centers','id',$centerIds);
}
echo "$count comprobaciones; ".count($failures)." fallos.\n";
exit($failures?1:0);
