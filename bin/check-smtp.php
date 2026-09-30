<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require_once dirname(__DIR__).'/app/Database.php';
$config=Database::config();
foreach(['Exception','SMTP','PHPMailer'] as $name)require_once dirname(__DIR__).'/vendor/phpmailer/phpmailer/src/'.$name.'.php';
$mail=new \PHPMailer\PHPMailer\PHPMailer(true);
try {
    $mail->isSMTP();$mail->Host=$config['smtp_host'];$mail->Port=(int)$config['smtp_port'];
    $mail->SMTPAuth=!empty($config['smtp_username']);$mail->Username=$config['smtp_username'];$mail->Password=$config['smtp_password'];
    $mail->SMTPSecure=$config['smtp_secure'];$mail->Timeout=20;$mail->Timelimit=30;$mail->SMTPDebug=0;
    if(!$mail->smtpConnect())throw new RuntimeException('Conexión rechazada.');
    $mail->smtpClose();echo "SMTP: conexión cifrada y autenticación correctas. No se envió ningún correo.\n";
} catch(Throwable $e) {
    $mail->smtpClose();fwrite(STDERR,"SMTP: no se pudo completar la conexión/autenticación. Revisa red, TLS y credenciales. No se envió ningún correo.\n");exit(1);
}
