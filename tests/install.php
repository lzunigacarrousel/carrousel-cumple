<?php
declare(strict_types=1);

// Run before production.php: this suite resets only the isolated test database.
$root=dirname(__DIR__);
require_once "$root/app/Database.php";
require_once "$root/app/Identity.php";
$cfg=require "$root/config/test.php";
if(($cfg['db_name']??'')!=='carrousel_cumple_test')throw new RuntimeException('Solo base de pruebas.');
$db=Database::connect($cfg);
if($db->query('SELECT DATABASE()')->fetchColumn()!=='carrousel_cumple_test')throw new RuntimeException('Solo base de pruebas.');
$db->exec(file_get_contents("$root/database/schema.sql"));
$db->exec('SET FOREIGN_KEY_CHECKS=0');
try {
    foreach(['audit','mail_queue','coupons','registration_requests','customers','campaigns','otp_codes','request_limits','user_centers','users','roles','cost_centers','runtime_state'] as $table)$db->exec("DELETE FROM `$table`");
} finally { $db->exec('SET FOREIGN_KEY_CHECKS=1'); }

$count=0;
function installCheck(bool $ok,string $message):void {
    global $count;
    if(!$ok)throw new RuntimeException('FAIL '.$message);
    echo "PASS $message\n";
    $count++;
}
function startAdminCommand(array $arguments):array {
    global $root;
    $environment=getenv();
    $environment['CUMPLE_CONFIG']="$root/config/test.php";
    $pipes=[];
    $process=proc_open([PHP_BINARY,"$root/bin/create-admin.php",...$arguments],[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes,$root,$environment,['bypass_shell'=>true]);
    if(!is_resource($process))throw new RuntimeException('No se pudo ejecutar create-admin.');
    fclose($pipes[0]);
    return [$process,$pipes];
}
function finishAdminCommand(array $job):array {
    [$process,$pipes]=$job;
    $output=stream_get_contents($pipes[1]);$error=stream_get_contents($pipes[2]);
    fclose($pipes[1]);fclose($pipes[2]);
    return ['exit'=>proc_close($process),'output'=>$output,'error'=>$error];
}
function adminSnapshot(PDO $db):array {
    $snapshot=[];
    foreach(['users','roles','cost_centers','audit','otp_codes','mail_queue'] as $table)$snapshot[$table]=$db->query("SELECT * FROM `$table` ORDER BY id")->fetchAll();
    return $snapshot;
}

// Removing input validation would create a privileged account for these cases.
$before=adminSnapshot($db);
$invalid=[
    'sin argumentos'=>[],
    'nombre vacío'=>['--name',' ','--email','admin@example.test'],
    'nombre demasiado largo'=>['--name',str_repeat('A',141),'--email','admin@example.test'],
    'correo inválido'=>['--name','Admin QA','--email','correo-invalido'],
    'nombre con salto de línea'=>['--name',"Admin\nQA",'--email','admin@example.test'],
    'opción desconocida'=>['--name','Admin QA','--email','admin@example.test','--force'],
    'opción duplicada'=>['--name','Admin QA','--name','Otro','--email','admin@example.test'],
];
foreach($invalid as $label=>$arguments){
    $result=finishAdminCommand(startAdminCommand($arguments));
    installCheck($result['exit']!==0 && trim($result['error'])!=='' && adminSnapshot($db)===$before,'Alta inicial rechaza '.$label.' sin cambios');
}
$help=finishAdminCommand(startAdminCommand(['--help']));
installCheck($help['exit']===0 && str_contains($help['output'],'--email') && adminSnapshot($db)===$before,'Ayuda CLI disponible sin crear cuentas');

$db->exec("INSERT INTO roles(code,name,permissions_json,is_system) VALUES('PARQUE','Parque QA','[]',0)");
$db->exec("INSERT INTO cost_centers(code,name,park_name) VALUES('QA-INSTALL','Centro QA','Parque QA')");
$catalogBefore=$db->query('SELECT * FROM cost_centers ORDER BY id')->fetchAll();
$roleBefore=$db->query("SELECT * FROM roles WHERE code='PARQUE'")->fetch();
$result=finishAdminCommand(startAdminCommand(['--name','  Admin inicial QA  ','--email=INITIAL@EXAMPLE.TEST']));
installCheck($result['exit']===0 && $result['error']==='','Primer administrador se crea desde CLI');
$user=$db->query('SELECT * FROM users')->fetch();
installCheck($user && $user['name']==='Admin inicial QA' && $user['email']==='initial@example.test' && (int)$user['active']===1 && $user['center_id']===null,'Cuenta normalizada y activa sin centro inventado');
$role=$db->query("SELECT * FROM roles WHERE code='ADMINISTRADOR'")->fetch();
$permissions=json_decode($role['permissions_json'],true,512,JSON_THROW_ON_ERROR);
installCheck((int)$role['id']===(int)$user['role_id'] && (int)$role['is_system']===1 && $permissions===array_keys(Identity::PERMISSIONS),'Rol administrador contiene todos los permisos');
$audit=$db->query('SELECT * FROM audit')->fetch();
installCheck($audit && $audit['event']==='INITIAL_ADMIN_CREATED' && $audit['entity']==='user' && (int)$audit['entity_id']===(int)$user['id'] && (int)$audit['actor_id']===(int)$user['id'],'Creación inicial queda en la auditoría');
installCheck((int)$db->query('SELECT COUNT(*) FROM otp_codes')->fetchColumn()===0 && (int)$db->query('SELECT COUNT(*) FROM mail_queue')->fetchColumn()===0,'Alta inicial no solicita OTP ni encola correo');
installCheck($db->query('SELECT * FROM cost_centers ORDER BY id')->fetchAll()===$catalogBefore && $db->query("SELECT * FROM roles WHERE code='PARQUE'")->fetch()===$roleBefore,'Alta inicial conserva otros roles y centros');

$before=adminSnapshot($db);
$again=finishAdminCommand(startAdminCommand(['--name','Segundo QA','--email','second@example.test']));
installCheck($again['exit']!==0 && trim($again['error'])!=='' && adminSnapshot($db)===$before,'Segunda ejecución rechazada sin alterar catálogos');
$db->exec("UPDATE users SET active=0,role_id=(SELECT id FROM roles WHERE code='PARQUE')");
$before=adminSnapshot($db);
$again=finishAdminCommand(startAdminCommand(['--name','Segundo QA','--email','second@example.test']));
installCheck($again['exit']!==0 && adminSnapshot($db)===$before,'Cuenta previa inactiva y no administradora también bloquea el alta inicial');

// Two independent CLI processes must never create two initial administrators.
$db->exec('DELETE FROM audit');$db->exec('DELETE FROM users');
$jobs=[startAdminCommand(['--name','Concurrente A','--email','race-a@example.test']),startAdminCommand(['--name','Concurrente B','--email','race-b@example.test'])];
$results=array_map('finishAdminCommand',$jobs);
installCheck(count(array_filter($results,fn($r)=>$r['exit']===0))===1 && (int)$db->query('SELECT COUNT(*) FROM users')->fetchColumn()===1 && (int)$db->query('SELECT COUNT(*) FROM audit')->fetchColumn()===1,'Dos altas iniciales concurrentes producen una cuenta y una auditoría');
echo "$count comprobaciones de instalación correctas.\n";
