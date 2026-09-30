<?php
declare(strict_types=1);
$root=dirname(__DIR__);foreach(['Database','Mailer','Identity','Program','Delivery'] as $class)require_once "$root/app/$class.php";
$cfg=require "$root/config/test.php";if($cfg['db_name']!=='carrousel_cumple_test')exit(2);date_default_timezone_set($cfg['timezone']);$db=Database::connect($cfg);$m=new Mailer($cfg,function($message)use($root){file_put_contents("$root/storage/race-mail.log",$message['messageId']."\n",FILE_APPEND|LOCK_EX);usleep(100000);});$identity=new Identity($db,$cfg,$m);$p=new Program($db,$identity);
while(microtime(true)<(float)$argv[2])usleep(1000);
try { $result=match($argv[1]){'issue'=>$p->issue(),'redeem'=>$p->redeem($identity->user((int)$argv[4]),$argv[3]),'send'=>(new Delivery($db,$cfg,$m,$p))->run(),default=>null};echo json_encode(['ok'=>true,'result'=>$result]); }
catch(DomainException $e){echo json_encode(['ok'=>false,'reason'=>$e->getMessage()]);}
