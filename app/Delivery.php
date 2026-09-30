<?php
declare(strict_types=1);

final class Delivery
{
    public function __construct(private PDO $db,private array $config,private Mailer $mailer,private Program $program) {}
    public function run(int $limit=50): array
    {
        // A worker that disappeared after DATA may already have sent the message.
        $this->db->exec("UPDATE mail_queue SET status='uncertain',last_error='El proceso se interrumpió durante el envío. Revisión requerida.' WHERE status='processing' AND claimed_at<DATE_SUB(NOW(),INTERVAL 10 MINUTE)");
        $counts=['accepted'=>0,'failed'=>0,'uncertain'=>0,'canceled'=>0];
        $url=parse_url($this->config['app_url']??'');
        if(($url['scheme']??'')!=='https'||in_array($url['host']??'',['','localhost','127.0.0.1'],true))throw new RuntimeException('Configura una URL pública HTTPS para enviar los cupones.');
        for($i=0;$i<$limit;$i++){
            $job=Database::transaction($this->db,function(){
                $q=$this->db->query("SELECT * FROM mail_queue WHERE status IN ('pending','failed') AND attempts<5 AND next_attempt_at<=NOW() ORDER BY id LIMIT 1 FOR UPDATE");$row=$q->fetch();if(!$row)return null;
                $token=bin2hex(random_bytes(24));$this->db->prepare("UPDATE mail_queue SET status='processing',claimed_at=NOW(),claim_token=?,attempts=attempts+1 WHERE id=?")->execute([$token,$row['id']]);$row['claim_token']=$token;return $row;
            });
            if(!$job)break;
            $c=$this->program->coupon((int)$job['coupon_id']);$error=null;$status='accepted';
            if(!$c||!(int)$c['customer_active']||$c['status']!=='available'||$c['email']!==$job['recipient']){$status='canceled';$error='Cliente o cupón no disponible, o correo actualizado.';}
            else {
                try{$this->mailer->sendCoupon($c,$job['recipient'],$job['message_id']);}
                catch(DeliveryFailure $e){$status=$e->uncertain?'uncertain':'failed';$error=$e->getMessage();}
                catch(Throwable $e){$status='uncertain';$error='El proceso de envío terminó sin confirmación. Revisión requerida.';}
            }
            $next=date('Y-m-d H:i:s',time()+min(3600,300*(1+(int)$job['attempts'])));
            $this->db->prepare('UPDATE mail_queue SET status=?,last_error=?,sent_at=?,next_attempt_at=?,claim_token=NULL WHERE id=? AND claim_token=?')->execute([$status,$error,$status==='accepted'?date('Y-m-d H:i:s'):null,$next,$job['id'],$job['claim_token']]);$counts[$status]++;
            Database::audit($this->db,null,'MAIL_'.strtoupper($status),'mail',$job['id']);
        }
        $this->db->prepare("INSERT INTO runtime_state(state_key,state_value) VALUES('worker_last_run',?) ON DUPLICATE KEY UPDATE state_value=VALUES(state_value)")->execute([date('Y-m-d H:i:s')]);return $counts;
    }
    public function listing(): array
    {
        $items=$this->db->query('SELECT q.id,c.code,q.recipient,q.status,q.attempts,q.last_error,q.created_at,q.sent_at FROM mail_queue q JOIN coupons c ON c.id=q.coupon_id ORDER BY q.id DESC LIMIT 500')->fetchAll();
        $last=$this->db->query("SELECT state_value FROM runtime_state WHERE state_key='worker_last_run'")->fetchColumn();return ['items'=>$items,'health'=>$this->mailer->health()+['worker_last_run'=>$last?:null]];
    }
    public function retry(array $u,int $id): array
    {
        return Database::transaction($this->db,function()use($u,$id){
            $q=$this->db->prepare('SELECT * FROM mail_queue WHERE id=? FOR UPDATE');$q->execute([$id]);$job=$q->fetch();
            if(!$job||!in_array($job['status'],['failed','uncertain'],true))throw new DomainException('Solo se reintentan envíos fallidos o inciertos.');
            $c=$this->program->coupon((int)$job['coupon_id']);if(!$c||$c['status']!=='available'||!(int)$c['customer_active'])throw new DomainException('El regalo ya no está disponible para enviar.');
            $this->db->prepare("UPDATE mail_queue SET status='pending',attempts=0,next_attempt_at=NOW(),claim_token=NULL,last_error=NULL WHERE id=?")->execute([$id]);Database::audit($this->db,$u['id'],'MAIL_RETRY','mail',$id,['previous_status'=>$job['status']]);return ['id'=>$id];
        });
    }
}
