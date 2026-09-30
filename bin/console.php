<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
$root=dirname(__DIR__);
foreach(['Database','Mailer','Identity','ImportCatalog'] as $class)require_once $root.'/app/'.$class.'.php';
$config=Database::config();date_default_timezone_set($config['timezone']);$db=Database::connect($config);
$command=$argv[1]??'help';
switch($command){
    case 'migrate': $db->exec(file_get_contents($root.'/database/schema.sql'));echo "Esquema instalado/actualizado.\n";break;
    case 'import-catalog': echo json_encode(ImportCatalog::import($db,$argv[2]??''),JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE)."\n";break;
    case 'health':
        $counts=[];foreach(['users','cost_centers','customers','campaigns','coupons'] as $table)$counts[$table]=(int)$db->query("SELECT COUNT(*) FROM $table")->fetchColumn();
        echo json_encode(['database'=>'connected','counts'=>$counts,'smtp'=>(new Mailer($config))->health(),'automation_enabled'=>$config['automation_enabled']],JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE)."\n";break;
    case 'run':
        require_once $root.'/app/Program.php';require_once $root.'/app/Delivery.php';
        if(empty($config['automation_enabled'])){echo "Automatización deshabilitada en configuración.\n";exit;}
        $mailer=new Mailer($config);$identity=new Identity($db,$config,$mailer);$program=new Program($db,$identity);$delivery=new Delivery($db,$config,$mailer,$program);
        $issued=$program->issue();$sent=$delivery->run();echo json_encode(['issued'=>$issued]+$sent)."\n";break;
    default: echo "Comandos: migrate | import-catalog RUTA_SQL | health | run\n";
}
