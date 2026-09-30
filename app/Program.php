<?php
declare(strict_types=1);

final class Program
{
    private DateTimeImmutable $now;
    public function __construct(private PDO $db,private Identity $identity,?DateTimeImmutable $now=null) { $this->now=$now??new DateTimeImmutable(); }
    private static function text(array $p,string $key,int $max,bool $required=true): string
    {
        $value=trim((string)($p[$key]??''));if(($required&&$value==='')||mb_strlen($value)>$max)throw new DomainException('Revisa el campo '.$key.'.');return $value;
    }
    public function register(array $u,array $p): array
    {
        $this->identity->require($u,'customers.write');$center=$this->identity->center($u,$p);
        $name=self::text($p,'name',140);$email=strtolower(self::text($p,'email',190));$phone=self::text($p,'phone',30,false);$guardian=self::text($p,'guardian',140,false);
        $day=filter_var($p['day']??null,FILTER_VALIDATE_INT);$month=filter_var($p['month']??null,FILTER_VALIDATE_INT);$minor=!empty($p['is_minor']);
        if(!$day||!$month||!checkdate($month,$day,2000))throw new DomainException('La fecha de cumpleaños no existe.');
        if(!filter_var($email,FILTER_VALIDATE_EMAIL))throw new DomainException('Escribe el correo donde el cliente recibirá su regalo.');
        if($minor&&!$guardian)throw new DomainException('Indica el adulto responsable del menor.');
        if(empty($p['terms_accepted']))throw new DomainException('Registra la aceptación del programa y del regalo por correo.');
        $request=self::text($p,'request_id',80);if(!preg_match('/^[a-zA-Z0-9_-]{4,80}$/',$request))throw new DomainException('Identificador de registro inválido. Recarga el formulario.');
        $key=hash('sha256',mb_strtolower(preg_replace('/\s+/u',' ',$name)).'|'.$day.'|'.$month.'|'.$email);
        $marketing=!empty($p['marketing_opt_in']);$payloadHash=hash('sha256',json_encode([$name,$day,$month,$email,$phone,$guardian,$minor,$marketing]));
        return Database::transaction($this->db,function()use($u,$center,$name,$email,$phone,$guardian,$day,$month,$minor,$request,$key,$payloadHash,$marketing){
            $this->db->prepare('SELECT id FROM users WHERE id=? FOR UPDATE')->execute([$u['id']]);
            $q=$this->db->prepare('SELECT * FROM registration_requests WHERE user_id=? AND request_id=?');$q->execute([$u['id'],$request]);$existing=$q->fetch();
            if($existing){if(!hash_equals($existing['payload_hash'],$payloadHash))throw new DomainException('Ese envío ya se utilizó con otros datos. Recarga el formulario.');return ['customer'=>$this->registrationView((int)$existing['customer_id'],$center),'duplicate'=>true];}
            $stamp=$this->now->format('Y-m-d H:i:s');
            $q=$this->db->prepare('INSERT IGNORE INTO customers(identity_key,name,day,month,email,phone,guardian,is_minor,terms_accepted_at,terms_version,marketing_opt_in,registered_by,center_id,created_at,updated_at) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)');
            $q->execute([$key,$name,$day,$month,$email,$phone,$guardian,$minor?1:0,$stamp,'programa-2026-09',$marketing?1:0,$u['id'],$center,$stamp,$stamp]);$duplicate=$q->rowCount()===0;
            $q=$this->db->prepare('SELECT id FROM customers WHERE identity_key=?');$q->execute([$key]);$id=(int)$q->fetchColumn();
            $this->db->prepare('INSERT INTO registration_requests(user_id,request_id,payload_hash,customer_id) VALUES(?,?,?,?)')->execute([$u['id'],$request,$payloadHash,$id]);
            if(!$duplicate)Database::audit($this->db,$u['id'],'CUSTOMER_REGISTERED','customer',$id,['center_id'=>$center]);
            // The operator supplied these details; cross-center duplicates return only an acknowledgement.
            $customer=$this->registrationView($id,$center);
            return ['customer'=>$customer,'duplicate'=>$duplicate];
        });
    }
    private function registrationView(int $id,int $center): array
    {
        $customer=$this->customer($id);
        return $customer['center_id']===$center?$customer:['id'=>$id,'name'=>$customer['name']];
    }
    private function customer(int $id): array
    {
        $q=$this->db->prepare('SELECT c.*,cc.name center_name,u.name registered_by_name FROM customers c JOIN cost_centers cc ON cc.id=c.center_id JOIN users u ON u.id=c.registered_by WHERE c.id=?');$q->execute([$id]);$r=$q->fetch();if(!$r)throw new DomainException('Cliente no encontrado.',404);unset($r['identity_key']);$r['center_id']=(int)$r['center_id'];$r['active']=(bool)$r['active'];$r['is_minor']=(bool)$r['is_minor'];return $r;
    }
    private function scopeSql(array $u,string $column,array &$params): string
    {
        $ids=$this->identity->scope($u);if($ids===null)return '1=1';if(!$ids)return '1=0';array_push($params,...$ids);return $column.' IN ('.implode(',',array_fill(0,count($ids),'?')).')';
    }
    public function customers(array $u,string $search=''): array
    {
        $this->identity->require($u,'customers.read');$params=[];$where=$this->scopeSql($u,'c.center_id',$params);
        if($search!==''){$where.=' AND (c.name LIKE ? OR c.email LIKE ?)';array_push($params,'%'.$search.'%','%'.$search.'%');}
        $q=$this->db->prepare("SELECT c.id,c.name,c.day,c.month,c.email,c.phone,c.guardian,c.is_minor,c.active,c.center_id,cc.name center_name,u.name registered_by_name,c.created_at FROM customers c JOIN cost_centers cc ON cc.id=c.center_id JOIN users u ON u.id=c.registered_by WHERE $where ORDER BY c.id DESC LIMIT 500");$q->execute($params);$rows=$q->fetchAll();foreach($rows as &$r){$r['active']=(bool)$r['active'];$r['is_minor']=(bool)$r['is_minor'];}return $rows;
    }
    public function customerStatus(array $u,int $id,bool $active): array
    {
        $this->identity->require($u,'customers.write');$c=$this->customer($id);$scope=$this->identity->scope($u);if($scope!==null&&!in_array($c['center_id'],$scope,true))throw new DomainException('Cliente fuera de tu alcance.',403);
        return Database::transaction($this->db,function()use($u,$id,$active){$this->db->prepare('UPDATE customers SET active=?,updated_at=NOW() WHERE id=?')->execute([$active?1:0,$id]);if(!$active)$this->db->prepare("UPDATE mail_queue q JOIN coupons c ON c.id=q.coupon_id SET q.status='canceled',q.last_error='Cliente inactivo' WHERE c.customer_id=? AND q.status IN ('pending','failed')")->execute([$id]);Database::audit($this->db,$u['id'],'CUSTOMER_STATUS','customer',$id,['active'=>$active]);return ['id'=>$id,'active'=>$active];});
    }
    public function campaigns(): array { return $this->db->query('SELECT * FROM campaigns ORDER BY id DESC')->fetchAll(); }
    public function saveCampaign(array $u,array $p): array
    {
        $this->identity->require($u,'campaigns.manage');$id=(int)($p['id']??0);$status=$p['status']??'draft';$type=$p['benefit_type']??'other';
        if(!in_array($status,['draft','active','paused'],true)||!in_array($type,['tokens','product','discount','other'],true))throw new DomainException('Tipo o estado de campaña inválido.');
        $quantity=($p['quantity']??'')===''||$p['quantity']===null?null:filter_var($p['quantity'],FILTER_VALIDATE_FLOAT);
        if($quantity!==null&&($quantity===false||$quantity<=0||$quantity>99999999))throw new DomainException('La cantidad debe ser positiva.');
        if($type==='tokens'&&($quantity===null||floor($quantity)!=$quantity))throw new DomainException('Define una cantidad entera positiva de tokens.');
        $dates=[];foreach(['start_date','end_date'] as $key){$s=trim((string)($p[$key]??''));$d=DateTimeImmutable::createFromFormat('!Y-m-d',$s);if($s!==''&&(!$d||$d->format('Y-m-d')!==$s))throw new DomainException('Fechas de campaña inválidas.');$dates[]=$s?:null;}
        if($dates[0]&&$dates[1]&&$dates[0]>$dates[1])throw new DomainException('La fecha final debe ser posterior al inicio.');
        $values=[self::text($p,'title',140),$type,self::text($p,'benefit_label',250),$quantity,self::text($p,'terms',5000),self::text($p,'email_subject',180),self::text($p,'email_intro',3000),...$dates,$status];
        return Database::transaction($this->db,function()use($u,$id,$values){
            if($id){$q=$this->db->prepare('SELECT id FROM campaigns WHERE id=? FOR UPDATE');$q->execute([$id]);if(!$q->fetchColumn())throw new DomainException('Campaña no encontrada.',404);$values[]=$id;$this->db->prepare('UPDATE campaigns SET title=?,benefit_type=?,benefit_label=?,quantity=?,terms=?,email_subject=?,email_intro=?,start_date=?,end_date=?,status=?,version=version+1,updated_at=NOW() WHERE id=?')->execute($values);}
            else {$this->db->prepare('INSERT INTO campaigns(title,benefit_type,benefit_label,quantity,terms,email_subject,email_intro,start_date,end_date,status) VALUES(?,?,?,?,?,?,?,?,?,?)')->execute($values);$id=(int)$this->db->lastInsertId();}
            Database::audit($this->db,$u['id'],'CAMPAIGN_SAVED','campaign',$id,['status'=>$values[9]]);$q=$this->db->prepare('SELECT * FROM campaigns WHERE id=?');$q->execute([$id]);return $q->fetch();
        });
    }
    public static function window(int $year,int $month,int $day): array
    {
        if($month===2&&$day===29&&!checkdate(2,29,$year))$day=28;
        $birthday=new DateTimeImmutable(sprintf('%04d-%02d-%02d',$year,$month,$day));return [$birthday->modify('-15 days')->format('Y-m-d'),$birthday->modify('+15 days')->format('Y-m-d')];
    }
    public function issue(): int
    {
        $today=$this->now->format('Y-m-d');$year=(int)$this->now->format('Y');$count=0;
        $q=$this->db->prepare("SELECT id FROM campaigns WHERE status='active' AND (start_date IS NULL OR start_date<=?) AND (end_date IS NULL OR end_date>=?)");$q->execute([$today,$today]);$campaigns=$q->fetchAll(PDO::FETCH_COLUMN);
        $customers=$this->db->query('SELECT id,day,month FROM customers WHERE active=1')->fetchAll();
        foreach($customers as $customer)foreach([$year-1,$year,$year+1] as $birthdayYear){
            [$from,$until]=self::window($birthdayYear,(int)$customer['month'],(int)$customer['day']);if($today<$from||$today>$until)continue;
            foreach($campaigns as $campaignId)$count+=Database::transaction($this->db,function()use($customer,$birthdayYear,$campaignId,$today,$from,$until){
                $q=$this->db->prepare('SELECT * FROM customers WHERE id=? AND active=1 FOR UPDATE');$q->execute([$customer['id']]);$client=$q->fetch();if(!$client)return 0;
                $q=$this->db->prepare("SELECT * FROM campaigns WHERE id=? AND status='active' FOR UPDATE");$q->execute([$campaignId]);$campaign=$q->fetch();if(!$campaign||($campaign['start_date']&&$campaign['start_date']>$today)||($campaign['end_date']&&$campaign['end_date']<$today))return 0;
                $q=$this->db->prepare('SELECT id FROM coupons WHERE customer_id=? AND campaign_id=? AND birthday_year=?');$q->execute([$client['id'],$campaignId,$birthdayYear]);if($q->fetchColumn())return 0;
                $code='CUM-'.strtoupper(bin2hex(random_bytes(8)));$token=bin2hex(random_bytes(32));$stamp=$this->now->format('Y-m-d H:i:s');
                $this->db->prepare('INSERT INTO coupons(customer_id,campaign_id,birthday_year,code,token,benefit_type,benefit_label,quantity,terms,email_subject,email_intro,valid_from,valid_until,created_at) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?)')->execute([$client['id'],$campaignId,$birthdayYear,$code,$token,$campaign['benefit_type'],$campaign['benefit_label'],$campaign['quantity'],$campaign['terms'],$campaign['email_subject'],$campaign['email_intro'],$from,$until,$stamp]);
                $id=(int)$this->db->lastInsertId();$messageId='<cumple-'.$id.'-'.bin2hex(random_bytes(8)).'@carrousel-apps.com>';
                $this->db->prepare('INSERT INTO mail_queue(coupon_id,recipient,next_attempt_at,message_id,created_at) VALUES(?,?,?,?,?)')->execute([$id,$client['email'],$stamp,$messageId,$stamp]);Database::audit($this->db,null,'COUPON_ISSUED','coupon',$id);return 1;
            });
        }
        return $count;
    }
    private function couponSql(): string
    {
        return 'SELECT c.*,cl.name,cl.email,cl.phone,cl.guardian,cl.active customer_active,cl.center_id,cl.created_at registered_at,cl.registered_by,cc.name center_name,u.name registered_by_name,rc.name redeemed_center_name,ru.name redeemed_by_name FROM coupons c JOIN customers cl ON cl.id=c.customer_id JOIN cost_centers cc ON cc.id=cl.center_id JOIN users u ON u.id=cl.registered_by LEFT JOIN cost_centers rc ON rc.id=c.redeemed_center_id LEFT JOIN users ru ON ru.id=c.redeemed_by';
    }
    public function coupon(int $id): ?array { $q=$this->db->prepare($this->couponSql().' WHERE c.id=?');$q->execute([$id]);$r=$q->fetch();return $r?$this->status($r):null; }
    private function status(array $c): array
    {
        $today=$this->now->format('Y-m-d');$c['status']=$c['voided_at']?'void':($c['redeemed_at']?'redeemed':($today<$c['valid_from']?'upcoming':($today>$c['valid_until']?'expired':'available')));return $c;
    }
    private function staff(array $c): array { unset($c['token'],$c['email'],$c['phone'],$c['guardian'],$c['email_subject'],$c['email_intro']);return $c; }
    public function lookup(array $u,string $code): array
    {
        $this->identity->require($u,'coupons.read');$q=$this->db->prepare($this->couponSql().' WHERE c.code=?');$q->execute([strtoupper(trim($code))]);$c=$q->fetch();if(!$c)throw new DomainException('No encontramos ese código.',404);return $this->staff($this->status($c));
    }
    public function publicCoupon(string $token): array
    {
        if(!preg_match('/^[a-f0-9]{64}$/',$token))throw new DomainException('Cupón no encontrado.',404);
        $q=$this->db->prepare($this->couponSql().' WHERE c.token=?');$q->execute([$token]);$c=$q->fetch();if(!$c)throw new DomainException('Cupón no encontrado.',404);
        return array_intersect_key($this->status($c),array_flip(['code','name','benefit_label','benefit_type','quantity','terms','valid_from','valid_until','status']));
    }
    public function coupons(array $u,array $filters=[]): array
    {
        $this->identity->require($u,'coupons.read');$params=[];$first=$this->scopeSql($u,'cl.center_id',$params);$second=$this->scopeSql($u,'c.redeemed_center_id',$params);$where='('.$first.' OR '.$second.')';
        if(!empty($filters['q'])){$where.=' AND (cl.name LIKE ? OR c.code LIKE ?)';array_push($params,'%'.$filters['q'].'%','%'.$filters['q'].'%');}
        if(!empty($filters['status']))$where.=' AND '.$this->statusSql($filters['status'],$params);
        $q=$this->db->prepare($this->couponSql()." WHERE $where ORDER BY c.id DESC LIMIT 500");$q->execute($params);return array_map(fn($r)=>$this->staff($this->status($r)),$q->fetchAll());
    }
    private function statusSql(string $status,array &$params): string
    {
        $today=$this->now->format('Y-m-d');
        if($status==='void')return 'c.voided_at IS NOT NULL';
        if($status==='redeemed')return '(c.voided_at IS NULL AND c.redeemed_at IS NOT NULL)';
        $base='c.voided_at IS NULL AND c.redeemed_at IS NULL';
        if($status==='upcoming'){$params[]=$today;return "($base AND c.valid_from>?)";}
        if($status==='expired'){$params[]=$today;return "($base AND c.valid_until<?)";}
        if($status==='available'){array_push($params,$today,$today);return "($base AND c.valid_from<=? AND c.valid_until>=?)";}
        throw new DomainException('Estado de cupón inválido.');
    }
    public function redeem(array $u,string $code,array $input=[]): array
    {
        $this->identity->require($u,'coupons.redeem');$center=$this->identity->center($u,$input);
        return Database::transaction($this->db,function()use($u,$code,$center){
            $q=$this->db->prepare('SELECT * FROM coupons WHERE code=? FOR UPDATE');$q->execute([strtoupper(trim($code))]);$c=$q->fetch();if(!$c)throw new DomainException('No encontramos ese código.',404);
            if($this->status($c)['status']!=='available')throw new DomainException('El cupón no está disponible: revisa su vigencia o el canje previo.');
            $q=$this->db->prepare('UPDATE coupons SET redeemed_at=?,redeemed_by=?,redeemed_center_id=? WHERE id=? AND redeemed_at IS NULL AND voided_at IS NULL');$q->execute([$this->now->format('Y-m-d H:i:s'),$u['id'],$center,$c['id']]);if($q->rowCount()!==1)throw new DomainException('Este cupón ya fue utilizado.');
            Database::audit($this->db,$u['id'],'COUPON_REDEEMED','coupon',$c['id'],['center_id'=>$center]);return $this->staff($this->coupon((int)$c['id']));
        });
    }
    public function void(array $u,string $code,string $reason): array
    {
        $this->identity->require($u,'campaigns.manage');$reason=trim($reason);if(!$reason||mb_strlen($reason)>500)throw new DomainException('Indica un motivo de anulación.');
        return Database::transaction($this->db,function()use($u,$code,$reason){$q=$this->db->prepare('SELECT id,redeemed_at,voided_at FROM coupons WHERE code=? FOR UPDATE');$q->execute([strtoupper(trim($code))]);$c=$q->fetch();if(!$c||$c['redeemed_at']||$c['voided_at'])throw new DomainException('El cupón no admite anulación.');$this->db->prepare('UPDATE coupons SET voided_at=NOW(),voided_by=?,void_reason=? WHERE id=?')->execute([$u['id'],$reason,$c['id']]);Database::audit($this->db,$u['id'],'COUPON_VOIDED','coupon',$c['id'],['reason'=>$reason]);return $this->staff($this->coupon((int)$c['id']));});
    }
    public function dashboard(array $u): array
    {
        $coupons=[];$customers=0;$totals=['available'=>0,'redeemed'=>0];
        if(in_array('customers.read',$u['permissions'],true)){
            $params=[];$where=$this->scopeSql($u,'c.center_id',$params);
            $q=$this->db->prepare("SELECT COUNT(*) FROM customers c WHERE $where");$q->execute($params);$customers=(int)$q->fetchColumn();
        }
        if(in_array('coupons.read',$u['permissions'],true)){
            foreach(array_keys($totals) as $status){
                $params=[];$first=$this->scopeSql($u,'cl.center_id',$params);$second=$this->scopeSql($u,'c.redeemed_center_id',$params);$where="($first OR $second) AND ".$this->statusSql($status,$params);
                $q=$this->db->prepare("SELECT COUNT(*) FROM coupons c JOIN customers cl ON cl.id=c.customer_id WHERE $where");$q->execute($params);$totals[$status]=(int)$q->fetchColumn();
            }
            $coupons=array_slice($this->coupons($u),0,20);
        }
        $pending=in_array('mail.manage',$u['permissions'],true)?(int)$this->db->query("SELECT COUNT(*) FROM mail_queue WHERE status IN ('pending','failed','uncertain')")->fetchColumn():0;
        return ['stats'=>['customers'=>$customers,'available'=>$totals['available'],'redeemed'=>$totals['redeemed'],'pending_mail'=>$pending],'coupons'=>$coupons];
    }
}
