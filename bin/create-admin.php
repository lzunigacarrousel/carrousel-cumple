<?php
declare(strict_types=1);

if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require_once dirname(__DIR__).'/app/Database.php';
require_once dirname(__DIR__).'/app/Identity.php';

$usage='Uso: php bin/create-admin.php --name "Nombre de administración" --email "correo@ejemplo.com"';
$db=null;
$lockName=null;
$exitCode=0;
try {
    $arguments=array_slice($argv,1);
    if($arguments===['--help']){
        echo "$usage\nCrea el primer administrador únicamente cuando no existen usuarios. No envía correo.\n";
        exit(0);
    }
    $options=[];
    for($i=0;$i<count($arguments);$i++){
        if(!preg_match('/^--(name|email)(?:=(.*))?$/s',$arguments[$i],$match))throw new DomainException($usage);
        $key=$match[1];
        if(array_key_exists($key,$options))throw new DomainException('No repitas las opciones --name ni --email.');
        if(array_key_exists(2,$match))$value=$match[2];
        else {
            $value=$arguments[++$i]??null;
            if($value===null || str_starts_with($value,'--'))throw new DomainException($usage);
        }
        $options[$key]=$value;
    }
    $name=trim($options['name']??'');
    $email=strtolower(trim($options['email']??''));
    if($name==='' || !mb_check_encoding($name,'UTF-8') || mb_strlen($name)>140 || preg_match('/[\x00-\x1F\x7F]/u',$name))throw new DomainException('Escribe un nombre válido de hasta 140 caracteres, sin saltos de línea.');
    if(!filter_var($email,FILTER_VALIDATE_EMAIL) || strlen($email)>190)throw new DomainException('Escribe un correo electrónico válido de hasta 190 caracteres.');

    $config=Database::config();
    date_default_timezone_set($config['timezone']);
    $db=Database::connect($config);
    $database=(string)$db->query('SELECT DATABASE()')->fetchColumn();
    // Advisory locks are server-wide: include the actual selected database.
    $candidateLock='cumple:first-admin:'.substr(hash('sha256',$database),0,40);
    $query=$db->prepare('SELECT GET_LOCK(?,10)');
    $query->execute([$candidateLock]);
    if((int)$query->fetchColumn()!==1)throw new DomainException('Otra instalación está creando la cuenta inicial. Inténtalo nuevamente.');
    $lockName=$candidateLock;

    $id=Database::transaction($db,function()use($db,$name,$email):int {
        if($db->query('SELECT id FROM users LIMIT 1 FOR UPDATE')->fetchColumn()!==false)throw new DomainException('La base ya contiene usuarios. Administra las cuentas existentes desde la aplicación; no se creó otra cuenta inicial.');
        $query=$db->prepare('SELECT id FROM roles WHERE code=? FOR UPDATE');
        $query->execute(['ADMINISTRADOR']);
        $roleId=$query->fetchColumn();
        $permissions=json_encode(array_keys(Identity::PERMISSIONS),JSON_THROW_ON_ERROR);
        if($roleId!==false){
            $db->prepare('UPDATE roles SET permissions_json=?,is_system=1 WHERE id=?')->execute([$permissions,$roleId]);
        } else {
            $db->prepare('INSERT INTO roles(code,name,permissions_json,is_system) VALUES(?,?,?,1)')->execute(['ADMINISTRADOR','Administrador',$permissions]);
            $roleId=(int)$db->lastInsertId();
        }
        $db->prepare('INSERT INTO users(name,email,role_id,center_id,active) VALUES(?,?,?,NULL,1)')->execute([$name,$email,$roleId]);
        $id=(int)$db->lastInsertId();
        Database::audit($db,$id,'INITIAL_ADMIN_CREATED','user',$id,['source'=>'cli']);
        return $id;
    });
    echo "Administrador inicial creado (cuenta $id). Configura SMTP e ingresa con su correo y OTP. No se envió correo.\n";
} catch(DomainException $error){
    fwrite(STDERR,$error->getMessage()."\n");
    $exitCode=1;
} catch(Throwable $error){
    // Never print connection strings, credentials, SQL or exception traces.
    fwrite(STDERR,"No se pudo crear el administrador inicial. Revisa la configuración privada, la conexión y que hayas ejecutado bin/console.php migrate.\n");
    $exitCode=1;
} finally {
    if($db instanceof PDO && $lockName!==null){
        try { $query=$db->prepare('SELECT RELEASE_LOCK(?)');$query->execute([$lockName]); }
        catch(Throwable) { /* Disconnecting also releases the advisory lock. */ }
    }
}
exit($exitCode);
