<?php
declare(strict_types=1);
foreach(['Database','Mailer','Identity','Program','Delivery','Web'] as $class)require_once __DIR__.'/'.$class.'.php';
$config=Database::config();date_default_timezone_set($config['timezone']);Web::start($config);
$db=Database::connect($config);$mailer=new Mailer($config,$mailTransport??null);$identity=new Identity($db,$config,$mailer);$program=new Program($db,$identity);$delivery=new Delivery($db,$config,$mailer,$program);
