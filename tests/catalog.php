<?php
declare(strict_types=1);
$root=dirname(__DIR__);foreach(['Database','Mailer','Identity','ImportCatalog'] as $class)require_once "$root/app/$class.php";
$cfg=require "$root/config/test.php";if($cfg['db_name']!=='carrousel_cumple_test')exit(2);$db=Database::connect($cfg);
$n=random_int(500000000,900000000);$file="$root/storage/catalog-test-$n.sql";
$sql="INSERT INTO `roles` VALUES (1,'PARQUE','Parque');\nINSERT INTO `parks` VALUES ($n,'Parque QA','QA-$n','Centro QA',1);\nINSERT INTO `users` VALUES ($n,'catalog-$n@example.test','Usuario QA',1,'ACTIVE',1,NULL,NULL,NULL,NULL,NULL,NULL);\nINSERT INTO `user_assignments` VALUES (1,$n,NULL,$n,NULL,NULL,NULL,'ACTIVE');\nINSERT INTO `expenses` VALUES (1,'ESTO NO SE IMPORTA');\n";
file_put_contents($file,$sql);
try{
    $before=(int)$db->query('SELECT COUNT(*) FROM customers')->fetchColumn();
    $first=ImportCatalog::import($db,$file);$second=ImportCatalog::import($db,$file);
    if($first['users_added']!==1||$first['centers_added']!==1||$second['users_added']!==0||$second['centers_added']!==0)throw new RuntimeException('Importación no idempotente.');
    $q=$db->prepare('SELECT u.id,u.center_id,c.source_park_id FROM users u JOIN cost_centers c ON c.id=u.center_id WHERE u.source_user_id=?');$q->execute([$n]);$u=$q->fetch();
    if(!$u||(int)$u['source_park_id']!==$n)throw new RuntimeException('Relación con centro incorrecta.');
    if((int)$db->query('SELECT COUNT(*) FROM customers')->fetchColumn()!==$before)throw new RuntimeException('Importador modificó clientes.');
    echo "PASS importación selectiva, centro vigente e idempotencia\n";
}finally{
    $q=$db->prepare('SELECT id FROM users WHERE source_user_id=?');$q->execute([$n]);$id=$q->fetchColumn();
    if($id){$db->prepare('DELETE FROM user_centers WHERE user_id=?')->execute([$id]);$db->prepare('DELETE FROM users WHERE id=?')->execute([$id]);}
    $db->prepare('DELETE FROM cost_centers WHERE source_park_id=?')->execute([$n]);unlink($file);
}
