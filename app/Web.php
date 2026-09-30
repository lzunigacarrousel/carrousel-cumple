<?php
declare(strict_types=1);
final class Web
{
    public static function headers(): void
    {
        header('Cache-Control: no-store');header('X-Content-Type-Options: nosniff');header('X-Frame-Options: DENY');header('Referrer-Policy: no-referrer');
        header("Content-Security-Policy: default-src 'self'; script-src 'self'; style-src 'self' 'unsafe-inline'; img-src 'self' data:; connect-src 'self'; font-src 'self'; object-src 'none'; base-uri 'self'; form-action 'self'; frame-ancestors 'none'");
        header('Permissions-Policy: camera=(self), microphone=(), geolocation=()');
    }
    public static function start(array $config): void
    {
        self::headers();$host=parse_url('http://'.($_SERVER['HTTP_HOST']??''),PHP_URL_HOST);
        if(!is_string($host)||!in_array(strtolower($host),$config['allowed_hosts'],true))self::fail('Servidor no autorizado.',403);
        $storage=dirname(__DIR__).'/storage';if(!is_dir($storage.'/sessions'))mkdir($storage.'/sessions',0700,true);
        ini_set('display_errors','0');ini_set('log_errors','1');ini_set('error_log',$storage.'/php-errors.log');
        ini_set('session.use_strict_mode','1');ini_set('session.use_only_cookies','1');session_name('CARROUSEL_CUMPLE');session_save_path($storage.'/sessions');
        $https=!empty($_SERVER['HTTPS'])&&$_SERVER['HTTPS']!=='off';
        if(in_array($_SERVER['REMOTE_ADDR']??'',['127.0.0.1','::1'],true)&&($_SERVER['HTTP_X_FORWARDED_PROTO']??'')==='https')$https=true;
        $path=str_replace('\\','/',dirname(parse_url($_SERVER['REQUEST_URI'],PHP_URL_PATH)?:'/'));
        session_set_cookie_params(['httponly'=>true,'secure'=>$https,'samesite'=>'Lax','path'=>rtrim($path,'/').'/']);session_start();
        if(isset($_SESSION['last_seen'])&&time()-$_SESSION['last_seen']>28800){$_SESSION=[];session_regenerate_id(true);}
        $_SESSION['last_seen']=time();$_SESSION['csrf']??=bin2hex(random_bytes(32));$_SESSION['otp_binding']??=bin2hex(random_bytes(32));
    }
    public static function success(mixed $data): never {header('Content-Type: application/json; charset=utf-8');echo json_encode(['ok'=>true,'data'=>$data],JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR);exit;}
    public static function fail(string $message,int $status=422): never {http_response_code($status);header('Content-Type: application/json; charset=utf-8');echo json_encode(['ok'=>false,'error'=>$message],JSON_UNESCAPED_UNICODE);exit;}
    public static function input(): array
    {
        if(!hash_equals($_SESSION['csrf'],$_SERVER['HTTP_X_CSRF_TOKEN']??''))self::fail('La sesión cambió. Recarga la página.',403);
        $origin=$_SERVER['HTTP_ORIGIN']??'';
        if($origin!==''){$parts=parse_url($origin);$requestHost=$_SERVER['HTTP_HOST']??'';$originHost=($parts['host']??'').(isset($parts['port'])?':'.$parts['port']:'');if(!in_array($parts['scheme']??'',['http','https'],true)||strcasecmp($originHost,$requestHost)!==0)self::fail('Origen no permitido.',403);}
        if(!str_starts_with(strtolower($_SERVER['CONTENT_TYPE']??''),'application/json'))self::fail('Se requiere JSON.',415);
        $raw=file_get_contents('php://input',false,null,0,32769);if($raw===false||strlen($raw)>32768)self::fail('Solicitud demasiado grande.',413);
        try{$p=json_decode($raw,true,8,JSON_THROW_ON_ERROR);}catch(JsonException){self::fail('JSON inválido.');}
        if(!is_array($p))self::fail('Solicitud inválida.');
        foreach($p as $key=>$value){if(is_array($value)&&(!in_array($key,['permissions','scope_center_ids'],true)||!array_is_list($value)||count($value)>1000||count(array_filter($value,fn($v)=>!is_scalar($v)))>0))self::fail('Campos inválidos.');}
        return $p;
    }
    public static function query(string $key,int $max=200): string {$v=$_GET[$key]??'';if(!is_string($v)||mb_strlen($v)>$max)self::fail('Parámetro inválido.');return trim($v);}
    public static function currentUser(Identity $identity): ?array
    {
        if(empty($_SESSION['user_id']))return null;$u=$identity->user((int)$_SESSION['user_id']);
        if(!$u||$u['auth_version']!==($_SESSION['auth_version']??0)){unset($_SESSION['user_id'],$_SESSION['auth_version']);return null;}return $u;
    }
    public static function user(Identity $identity): array {return self::currentUser($identity)??self::fail('Inicia sesión para continuar.',401);}
}
