<?php
declare(strict_types=1);

final class DeliveryFailure extends RuntimeException
{
    public function __construct(string $message, public readonly bool $uncertain=false) { parent::__construct($message); }
}

final class Mailer
{
    public function __construct(private array $config, private ?Closure $transport=null) {}
    public function health(): array
    {
        $c=$this->config;
        return ['ready'=>!empty($c['smtp_host']) && filter_var($c['mail_from']??'',FILTER_VALIDATE_EMAIL)!==false && (empty($c['smtp_username']) || !empty($c['smtp_password'])) && in_array($c['smtp_secure']??'',['tls','ssl'],true),
            'from'=>$c['mail_from']??'', 'host'=>$c['smtp_host']??'', 'public_url'=>$c['app_url']??''];
    }
    public function sendOtp(string $email,string $code): void
    {
        $this->send($email,'Código de acceso | Cumpleaños Carrousel',$this->layout('Tu código de acceso','<p>Usa este código para ingresar a Cumpleaños Carrousel.</p><p style="font-size:36px;letter-spacing:8px;font-weight:bold">'.self::esc($code).'</p><p>Vence en 10 minutos. No lo compartas.</p>'),"Código: $code. Vence en 10 minutos. No lo compartas.",null);
    }
    public function sendCoupon(array $c,string $recipient,string $messageId): void
    {
        $quantity=isset($c['quantity'])?rtrim(rtrim(number_format((float)$c['quantity'],2,'.',''),'0'),'.'):'';
        $benefit=($quantity!==''?($c['benefit_type']==='tokens'?$quantity.' tokens · ':'Cantidad: '.$quantity.' · '):'').$c['benefit_label'];
        $url=rtrim($this->config['app_url'],'/').'/?'.http_build_query(['gift'=>$c['token']]);
        $body='<p>Hola, '.self::esc($c['name']).'.</p><p>'.nl2br(self::esc($c['email_intro'])).'</p><h2>'.self::esc($benefit).'</h2><p><strong>Vigencia:</strong> '.self::esc($c['valid_from']).' al '.self::esc($c['valid_until']).', ambos incluidos.</p><p>Presenta este código en cualquier parque Carrousel:</p><p style="font-size:24px;font-weight:bold">'.self::esc($c['code']).'</p><p>'.nl2br(self::esc($c['terms'])).'</p><p>Un solo canje en toda la red, previa validación de vigencia y uso.</p><p><a style="display:inline-block;padding:14px 20px;background:#213a8f;color:white;border-radius:8px" href="'.self::esc($url).'">Ver cupón y QR</a></p>';
        $text="Hola {$c['name']}.\n{$c['email_intro']}\n{$benefit}\nCódigo: {$c['code']}\nVigencia: {$c['valid_from']} al {$c['valid_until']}.\n{$c['terms']}\nUn solo uso en cualquier parque.\n$url";
        $this->send($recipient,$c['email_subject'],$this->layout('Tu regalo de cumpleaños',$body),$text,$messageId);
    }
    private static function esc(string $s): string { return htmlspecialchars($s,ENT_QUOTES,'UTF-8'); }
    private function layout(string $title,string $body): string
    {
        return '<!doctype html><html lang="es"><body style="margin:0;background:#eef3f9;font-family:Arial,sans-serif;color:#17233b"><table role="presentation" width="100%"><tr><td align="center" style="padding:28px 12px"><table role="presentation" style="max-width:620px;width:100%;background:white;border-radius:16px;border-top:5px solid #213a8f"><tr><td style="padding:30px"><img src="cid:carrousel-logo" width="108" alt="Carrousel"><h1>'.self::esc($title).'</h1>'.$body.'<hr><p style="color:#667085;font-size:12px">Corporación Carrousel · Programa de cumpleaños. Si necesitas corregir tus datos o dejar de participar, solicita la actualización al personal del parque.</p></td></tr></table></td></tr></table></body></html>';
    }
    private function send(string $to,string $subject,string $html,string $text,?string $messageId): void
    {
        if(!filter_var($to,FILTER_VALIDATE_EMAIL))throw new DeliveryFailure('Destinatario inválido.');
        if($this->transport) { ($this->transport)(compact('to','subject','html','text','messageId')); return; }
        if(!$this->health()['ready'])throw new DeliveryFailure('Configuración SMTP incompleta.');
        $base=dirname(__DIR__).'/vendor/phpmailer/phpmailer/src/';
        foreach(['Exception','SMTP','PHPMailer'] as $name) { if(!is_file($base.$name.'.php'))throw new DeliveryFailure('Falta PHPMailer. Revisa la instalación de dependencias.');require_once $base.$name.'.php'; }
        require_once __DIR__.'/TrackedSMTP.php';
        $smtp=new TrackedSMTP(); $mail=new \PHPMailer\PHPMailer\PHPMailer(true); $c=$this->config;
        try {
            $mail->isSMTP(); $mail->setSMTPInstance($smtp); $mail->Host=$c['smtp_host'];$mail->Port=(int)$c['smtp_port'];
            $mail->SMTPAuth=!empty($c['smtp_username']);$mail->Username=$c['smtp_username']??'';$mail->Password=$c['smtp_password']??'';
            $mail->SMTPSecure=$c['smtp_secure'];$mail->CharSet='UTF-8';$mail->Timeout=20;$mail->Timelimit=30;
            $mail->setFrom($c['mail_from'],$c['mail_from_name']);$mail->addAddress($to);
            $mail->Subject=preg_replace('/[\r\n]+/',' ',$subject);$mail->isHTML(true);$mail->Body=$html;$mail->AltBody=$text;
            if($messageId)$mail->MessageID=$messageId;
            $logo=dirname(__DIR__).'/public/assets/logo-carrousel.png';if(is_file($logo))$mail->addEmbeddedImage($logo,'carrousel-logo');
            $mail->send();
        } catch(Throwable $e) {
            $reply=trim($smtp->getLastReply());$explicitRejection=preg_match('/^[45]\d\d[ -]/',$reply)===1;
            $uncertain=$smtp->dataStarted && !$explicitRejection;
            throw new DeliveryFailure($uncertain?'Resultado SMTP incierto. Revisa el servidor antes de reintentar.':'El servidor SMTP no aceptó el mensaje. Revisa la conexión y configuración.',$uncertain);
        }
    }
}
