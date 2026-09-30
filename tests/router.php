<?php
declare(strict_types=1);
// Test server only: never deployed. Exercises real OTP without sending to real mailboxes.
if(!in_array($_SERVER['REMOTE_ADDR']??'',['127.0.0.1','::1'],true)) {http_response_code(403);exit;}
putenv('CUMPLE_CONFIG='.dirname(__DIR__).'/config/test.php');
$mailTransport=function(array $message):void {file_put_contents(dirname(__DIR__).'/storage/test-outbox.json',json_encode($message,JSON_THROW_ON_ERROR),LOCK_EX);};
return require dirname(__DIR__).'/public/router.php';
