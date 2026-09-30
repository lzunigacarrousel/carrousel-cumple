<?php
declare(strict_types=1);
$root=dirname(__DIR__);foreach(['Database','Mailer','Identity','Program','Delivery'] as $class)require_once "$root/app/$class.php";
$cfg=require "$root/config/test.php";if($cfg['db_name']!=='carrousel_cumple_test')throw new RuntimeException('Solo pruebas.');date_default_timezone_set($cfg['timezone']);$db=Database::connect($cfg);$mailer=new Mailer($cfg,fn($m)=>null);$identity=new Identity($db,$cfg,$mailer);$a=$identity->user(2);$b=$identity->user(3);$admin=$identity->user(1);$n=0;
function expect(bool $b,string $text):void{global $n;if(!$b)throw new RuntimeException($text);echo "PASS $text\n";$n++;}
function fails(callable $fn,string $text):void{try{$fn();}catch(DomainException){expect(true,$text);return;}throw new RuntimeException($text);}
foreach([
 ['2026-09-06',21,9,2026,'2026-09-06','2026-10-06'],
 ['2026-10-06',21,9,2026,'2026-09-06','2026-10-06'],
 ['2026-12-21',5,1,2027,'2026-12-21','2027-01-20'],
 ['2027-01-10',26,12,2026,'2026-12-11','2027-01-10'],
 ['2027-02-13',29,2,2027,'2027-02-13','2027-03-15'],
 ['2028-02-14',29,2,2028,'2028-02-14','2028-03-15'],
] as $i=>$t){
 [$today,$day,$month,$year,$from,$until]=$t;$p=new Program($db,$identity,new DateTimeImmutable($today.' 12:00:00'));
 $client=$p->register($a,['name'=>'Ventana '.$i.' '.uniqid(),'day'=>$day,'month'=>$month,'email'=>'edge@example.test','terms_accepted'=>true,'request_id'=>'edge-'.bin2hex(random_bytes(8))]);
 $p->issue();$q=$db->prepare('SELECT * FROM coupons WHERE customer_id=? AND birthday_year=? ORDER BY id LIMIT 1');$q->execute([$client['customer']['id'],$year]);$c=$q->fetch();expect($c&&$c['valid_from']===$from&&$c['valid_until']===$until,'Emisión en límite y año '.$i);
 expect($p->redeem($b,$c['code'])['status']==='redeemed','Canje válido en fecha límite '.$i);
}
$p=new Program($db,$identity,new DateTimeImmutable('2026-10-07 12:00:00'));$client=$p->register($a,['name'=>'Fuera de ventana '.uniqid(),'day'=>21,'month'=>9,'email'=>'out@example.test','terms_accepted'=>true,'request_id'=>'outside-'.uniqid()]);$p->issue();$q=$db->prepare('SELECT COUNT(*) FROM coupons WHERE customer_id=?');$q->execute([$client['customer']['id']]);expect((int)$q->fetchColumn()===0,'Alta fuera de ventana no emite');
$now=new DateTimeImmutable();$p=new Program($db,$identity,$now);$client=$p->register($a,['name'=>'Fallo correo '.uniqid(),'day'=>(int)$now->format('d'),'month'=>(int)$now->format('m'),'email'=>'fail@example.test','terms_accepted'=>true,'request_id'=>'mail-'.uniqid()]);$p->issue();
$uncertain=new Delivery($db,$cfg,new Mailer($cfg,function(){throw new DeliveryFailure('Prueba resultado incierto',true);}),$p);$r=$uncertain->run();expect($r['uncertain']>=1,'Resultado incierto se separa de fallo conocido');expect($uncertain->run()['uncertain']===0,'No reintenta automáticamente resultados inciertos');
$q=$db->query("SELECT id,coupon_id FROM mail_queue WHERE status='uncertain' ORDER BY id DESC LIMIT 1");$job=$q->fetch();$uncertain->retry($admin,(int)$job['id']);$q=$db->prepare('SELECT coupon_id,status FROM mail_queue WHERE id=?');$q->execute([$job['id']]);$after=$q->fetch();expect($after['coupon_id']===$job['coupon_id']&&$after['status']==='pending','Reintento conserva el cupón');
$failed=new Delivery($db,$cfg,new Mailer($cfg,function(){throw new DeliveryFailure('Prueba antes de SMTP');}),$p);expect($failed->run()['failed']>=1,'Fallo conocido queda para reintento diferido');
$db->prepare("UPDATE mail_queue SET status='processing',claimed_at=DATE_SUB(NOW(),INTERVAL 11 MINUTE) WHERE id=?")->execute([$job['id']]);$failed->run();$q=$db->prepare('SELECT status FROM mail_queue WHERE id=?');$q->execute([$job['id']]);expect($q->fetchColumn()==='uncertain','Interrupción de proceso no provoca reenvío ciego');
$messages=[];$otpIdentity=new Identity($db,$cfg,new Mailer($cfg,function($m)use(&$messages){$messages[]=$m;}));$request=$otpIdentity->requestOtp('b@example.test','attempt-limit','127.0.0.2');for($i=0;$i<5;$i++)fails(fn()=>$otpIdentity->verifyOtp($request['challenge'],'000000','attempt-limit','127.0.0.2'),'Intento inválido '.($i+1));preg_match('/Código: (\d{6})/',end($messages)['text'],$match);fails(fn()=>$otpIdentity->verifyOtp($request['challenge'],$match[1],'attempt-limit','127.0.0.2'),'Bloqueo después de cinco intentos');
$db->exec('UPDATE cost_centers SET active=0 WHERE id=2');fails(fn()=>$identity->center($b),'Centro desactivado bloquea operación');$db->exec('UPDATE cost_centers SET active=1 WHERE id=2');
echo "$n comprobaciones adicionales correctas.\n";
