<?php
declare(strict_types=1);

final class Identity
{
    public const PERMISSIONS = [
        'customers.read'=>'Consultar clientes','customers.write'=>'Registrar clientes',
        'coupons.read'=>'Consultar cupones','coupons.redeem'=>'Canjear cupones',
        'campaigns.manage'=>'Administrar campañas','mail.manage'=>'Administrar envíos',
        'users.manage'=>'Administrar usuarios','roles.manage'=>'Administrar roles',
        'centers.manage'=>'Administrar centros de costo','audit.read'=>'Consultar bitácora',
        'reports.global'=>'Consultar todos los centros',
    ];
    public function __construct(private PDO $db, private array $config, private Mailer $mailer) {}
    public function user(int $id): ?array
    {
        $q=$this->db->prepare('SELECT u.*,r.code role_code,r.name role_name,r.permissions_json,c.name center_name,c.code center_code,c.active center_active FROM users u JOIN roles r ON r.id=u.role_id LEFT JOIN cost_centers c ON c.id=u.center_id WHERE u.id=? AND u.active=1');
        $q->execute([$id]); $u=$q->fetch(); if (!$u) return null;
        $u['id']=(int)$u['id']; $u['role_id']=(int)$u['role_id']; $u['auth_version']=(int)$u['auth_version'];
        $u['center_id']=$u['center_id']===null?null:(int)$u['center_id'];
        $u['permissions']=$u['role_code']==='ADMINISTRADOR'?array_keys(self::PERMISSIONS):(json_decode($u['permissions_json'],true)?:[]);
        if ($u['role_code']!=='ADMINISTRADOR') $u['permissions']=array_values(array_diff($u['permissions'],['users.manage','roles.manage','centers.manage']));
        unset($u['permissions_json']); return $u;
    }
    public function require(array $u, string $permission): void
    {
        if (!in_array($permission,$u['permissions'],true)) throw new DomainException('Tu perfil no tiene permiso para esta acción.',403);
    }
    public function center(array $u, array $input=[]): int
    {
        $fresh=$this->user($u['id']);
        if (!$fresh || !$fresh['center_id'] || !(int)$fresh['center_active']) throw new DomainException('Tu cuenta necesita un centro de costo operativo activo. Solicita la asignación a administración.',403);
        foreach (['center_id','park_id'] as $field) if (isset($input[$field]) && (int)$input[$field]!==$fresh['center_id']) throw new DomainException('El centro de operación se obtiene de tu cuenta.',403);
        return $fresh['center_id'];
    }
    public function scope(array $u): ?array
    {
        if (in_array('reports.global',$u['permissions'],true)) return null;
        $q=$this->db->prepare('SELECT center_id FROM user_centers WHERE user_id=?'); $q->execute([$u['id']]);
        $ids=array_map('intval',$q->fetchAll(PDO::FETCH_COLUMN));
        if ($u['center_id']) $ids[]=$u['center_id'];
        return array_values(array_unique($ids));
    }
    public function throttle(string $key, int $limit, int $seconds): void
    {
        $bucket=intdiv(time(),$seconds); $hash=hash_hmac('sha256',$key,$this->config['app_key']);
        $this->db->prepare('INSERT INTO request_limits(limit_key,bucket,hits) VALUES(?,?,1) ON DUPLICATE KEY UPDATE hits=hits+1')->execute([$hash,$bucket]);
        $q=$this->db->prepare('SELECT hits FROM request_limits WHERE limit_key=? AND bucket=?'); $q->execute([$hash,$bucket]);
        if ((int)$q->fetchColumn()>$limit) throw new DomainException('Demasiados intentos. Espera unos minutos antes de intentar de nuevo.',429);
    }
    public function requestOtp(string $email, string $session, string $ip): array
    {
        $email=strtolower(trim($email));
        if (!filter_var($email,FILTER_VALIDATE_EMAIL) || strlen($email)>190) throw new DomainException('Escribe un correo válido.');
        $this->throttle('otp-ip:'.$ip,20,900); $this->throttle('otp-email:'.$email,5,900);
        $challenge=bin2hex(random_bytes(24)); $message='Si tu cuenta está habilitada, recibirás un código de acceso que vence en 10 minutos.';
        $result=Database::transaction($this->db,function() use($email,$challenge,$session) {
            $q=$this->db->prepare('SELECT id FROM users WHERE email=? AND active=1 FOR UPDATE'); $q->execute([$email]); $id=$q->fetchColumn();
            if (!$id) return null;
            $code=(string)random_int(100000,999999);
            $this->db->prepare('UPDATE otp_codes SET consumed_at=NOW() WHERE user_id=? AND consumed_at IS NULL')->execute([$id]);
            $this->db->prepare('INSERT INTO otp_codes(challenge,user_id,code_hash,session_hash,expires_at) VALUES(?,?,?,?,DATE_ADD(NOW(),INTERVAL 10 MINUTE))')
                ->execute([$challenge,$id,password_hash($code,PASSWORD_DEFAULT),hash_hmac('sha256',$session,$this->config['app_key'])]);
            return ['id'=>(int)$id,'code'=>$code];
        });
        if ($result) {
            try { $this->mailer->sendOtp($email,$result['code']); Database::audit($this->db,$result['id'],'OTP_REQUESTED','user',$result['id']); }
            catch(Throwable $e) { $this->db->prepare('UPDATE otp_codes SET consumed_at=NOW() WHERE challenge=?')->execute([$challenge]); throw new DomainException('No se pudo enviar el código. Revisa la configuración de correo con administración.',503); }
        }
        return compact('challenge','message');
    }
    public function verifyOtp(string $challenge, string $code, string $session, string $ip): array
    {
        $this->throttle('verify:'.$ip,60,900);
        if (!preg_match('/^[a-f0-9]{48}$/',$challenge)) throw new DomainException('Solicita un código nuevo.');
        $q=$this->db->prepare('SELECT user_id FROM otp_codes WHERE challenge=?');$q->execute([$challenge]);$userId=(int)$q->fetchColumn();
        $result=Database::transaction($this->db,function() use($challenge,$code,$session,$userId) {
            // All identity mutations lock the account before its challenges.
            $q=$this->db->prepare('SELECT id FROM users WHERE id=? FOR UPDATE');$q->execute([$userId]);$q->fetchColumn();
            $q=$this->db->prepare('SELECT *,expires_at<=NOW() expired FROM otp_codes WHERE challenge=? FOR UPDATE'); $q->execute([$challenge]); $o=$q->fetch();
            if (!$o || !hash_equals($o['session_hash'],hash_hmac('sha256',$session,$this->config['app_key'])) || $o['consumed_at'] || $o['expired'] || (int)$o['attempts']>=5) return ['error'=>'El código no está disponible. Solicita uno nuevo.'];
            if (!preg_match('/^\d{6}$/',$code) || !password_verify($code,$o['code_hash'])) {
                $this->db->prepare('UPDATE otp_codes SET attempts=attempts+1, consumed_at=IF(attempts>=5,NOW(),NULL) WHERE id=?')->execute([$o['id']]);
                return ['error'=>'Código incorrecto. Después de cinco intentos debes solicitar uno nuevo.'];
            }
            $this->db->prepare('UPDATE otp_codes SET consumed_at=NOW() WHERE id=?')->execute([$o['id']]);
            $u=$this->user((int)$o['user_id']); if (!$u) return ['error'=>'La cuenta ya no está habilitada.'];
            Database::audit($this->db,$u['id'],'LOGIN','user',$u['id']); return $u;
        });
        if (isset($result['error'])) throw new DomainException($result['error'],401);
        return $result;
    }
    public function roles(): array
    {
        $rows=$this->db->query('SELECT * FROM roles ORDER BY id')->fetchAll();
        foreach($rows as &$r) { $r['permissions']=$r['code']==='ADMINISTRADOR'?array_keys(self::PERMISSIONS):json_decode($r['permissions_json'],true); unset($r['permissions_json']); $r['is_system']=(bool)$r['is_system']; }
        return ['roles'=>$rows,'permissions'=>self::PERMISSIONS];
    }
    public function users(): array
    {
        $rows=$this->db->query('SELECT u.id,u.name,u.email,u.role_id,r.name role_name,u.center_id,c.name center_name,u.active FROM users u JOIN roles r ON r.id=u.role_id LEFT JOIN cost_centers c ON c.id=u.center_id ORDER BY u.name')->fetchAll();
        $q=$this->db->prepare('SELECT center_id FROM user_centers WHERE user_id=?');
        foreach($rows as &$u) { $q->execute([$u['id']]); $u['scope_center_ids']=array_map('intval',$q->fetchAll(PDO::FETCH_COLUMN)); $u['active']=(bool)$u['active']; }
        return $rows;
    }
    public function saveUser(array $actor,array $p): array
    {
        $this->require($actor,'users.manage');
        $name=trim((string)($p['name']??'')); $email=strtolower(trim((string)($p['email']??''))); $id=(int)($p['id']??0); $role=(int)($p['role_id']??0); $center=(int)($p['center_id']??0)?:null; $active=!empty($p['active'])?1:0;
        if (!$name || mb_strlen($name)>140 || !filter_var($email,FILTER_VALIDATE_EMAIL) || strlen($email)>190) throw new DomainException('Completa nombre y correo válidos.');
        $q=$this->db->prepare('SELECT code FROM roles WHERE id=?');$q->execute([$role]); $roleCode=$q->fetchColumn(); if (!$roleCode) throw new DomainException('Selecciona un rol válido.');
        if ($roleCode==='PARQUE' && !$center) throw new DomainException('La cuenta de parque requiere centro de costo.');
        $ids=array_unique(array_filter(array_map('intval',is_array($p['scope_center_ids']??null)?$p['scope_center_ids']:[]))); if ($center) $ids[]=$center;
        foreach($ids as $cid) { $q=$this->db->prepare('SELECT id FROM cost_centers WHERE id=? AND active=1');$q->execute([$cid]);if(!$q->fetchColumn())throw new DomainException('Selecciona centros de costo activos.'); }
        return Database::transaction($this->db,function()use($actor,$p,$id,$name,$email,$role,$roleCode,$center,$active,$ids){
            $this->db->query('SELECT id FROM roles ORDER BY id FOR UPDATE')->fetchAll();
            $admins=$this->db->query("SELECT u.id FROM users u JOIN roles r ON r.id=u.role_id WHERE r.code='ADMINISTRADOR' AND u.active=1 FOR UPDATE")->fetchAll(PDO::FETCH_COLUMN);
            if ($id && in_array($id,array_map('intval',$admins),true) && count($admins)===1 && (!$active || $roleCode!=='ADMINISTRADOR')) throw new DomainException('Debe conservarse al menos un administrador activo.');
            if ($id) {
                $q=$this->db->prepare('SELECT id FROM users WHERE id=?');$q->execute([$id]);if(!$q->fetchColumn())throw new DomainException('Usuario no encontrado.');
                $this->db->prepare('UPDATE users SET name=?,email=?,role_id=?,center_id=?,active=?,auth_version=auth_version+1 WHERE id=?')->execute([$name,$email,$role,$center,$active,$id]);
                $this->db->prepare('UPDATE otp_codes SET consumed_at=NOW() WHERE user_id=? AND consumed_at IS NULL')->execute([$id]);
            } else { $this->db->prepare('INSERT INTO users(name,email,role_id,center_id,active) VALUES(?,?,?,?,?)')->execute([$name,$email,$role,$center,$active]);$id=(int)$this->db->lastInsertId(); }
            $this->db->prepare('DELETE FROM user_centers WHERE user_id=?')->execute([$id]);
            foreach(array_unique($ids) as $cid)$this->db->prepare('INSERT INTO user_centers(user_id,center_id) VALUES(?,?)')->execute([$id,$cid]);
            Database::audit($this->db,$actor['id'],'USER_SAVED','user',$id,['role'=>$roleCode,'center_id'=>$center,'active'=>$active]); return ['id'=>$id];
        });
    }
    public function saveRole(array $actor,array $p): array
    {
        $this->require($actor,'roles.manage'); $id=(int)($p['id']??0); $code=strtoupper(trim((string)($p['code']??'')));$name=trim((string)($p['name']??''));
        if(!preg_match('/^[A-Z][A-Z0-9_]{2,39}$/',$code)||!$name||mb_strlen($name)>100)throw new DomainException('Completa nombre y código del rol.');
        $permissions=array_values(array_unique(is_array($p['permissions']??null)?$p['permissions']:[]));
        if(array_diff($permissions,array_keys(self::PERMISSIONS)))throw new DomainException('La matriz contiene permisos desconocidos.');
        if(array_intersect($permissions,['users.manage','roles.manage','centers.manage']))throw new DomainException('La administración estructural está reservada al Administrador.');
        if(in_array('coupons.redeem',$permissions,true))$permissions[]='coupons.read';
        if(in_array('customers.write',$permissions,true))$permissions[]='customers.read';
        $permissions=array_values(array_unique($permissions));
        return Database::transaction($this->db,function()use($actor,$id,$code,$name,$permissions){
            $this->db->query('SELECT id FROM roles ORDER BY id FOR UPDATE')->fetchAll();
            $q=$this->db->prepare('SELECT code FROM roles WHERE id=? FOR UPDATE');$q->execute([$id]);$old=$q->fetchColumn();
            if($code==='ADMINISTRADOR'||$old==='ADMINISTRADOR')throw new DomainException('El rol Administrador está protegido.');
            if($id&&!$old)throw new DomainException('Rol no encontrado.');
            if($id)$this->db->prepare('UPDATE roles SET code=?,name=?,permissions_json=? WHERE id=?')->execute([$code,$name,json_encode($permissions),$id]);
            else {$this->db->prepare('INSERT INTO roles(code,name,permissions_json) VALUES(?,?,?)')->execute([$code,$name,json_encode($permissions)]);$id=(int)$this->db->lastInsertId();}
            $this->db->prepare('UPDATE users SET auth_version=auth_version+1 WHERE role_id=?')->execute([$id]);
            $this->db->prepare('UPDATE otp_codes o JOIN users u ON u.id=o.user_id SET o.consumed_at=NOW() WHERE u.role_id=? AND o.consumed_at IS NULL')->execute([$id]);
            Database::audit($this->db,$actor['id'],'ROLE_SAVED','role',$id,['permissions'=>$permissions]);return ['id'=>$id];
        });
    }
    public function centers(): array { return $this->db->query('SELECT id,code,name,park_name,active FROM cost_centers ORDER BY code')->fetchAll(); }
    public function saveCenter(array $actor,array $p): array
    {
        $this->require($actor,'centers.manage');$id=(int)($p['id']??0);$values=[];
        foreach(['code'=>50,'name'=>160,'park_name'=>160] as $key=>$max) { $v=trim((string)($p[$key]??''));if(!$v||mb_strlen($v)>$max)throw new DomainException('Completa código, nombre y parque del centro de costo.');$values[]=$v; } $values[]=!empty($p['active'])?1:0;
        return Database::transaction($this->db,function()use($id,$values,$actor){
            if($id){$values[]=$id;$this->db->prepare('UPDATE cost_centers SET code=?,name=?,park_name=?,active=? WHERE id=?')->execute($values);}
            else {$this->db->prepare('INSERT INTO cost_centers(code,name,park_name,active) VALUES(?,?,?,?)')->execute($values);$id=(int)$this->db->lastInsertId();}
            Database::audit($this->db,$actor['id'],'CENTER_SAVED','center',$id);return ['id'=>$id];
        });
    }
}
