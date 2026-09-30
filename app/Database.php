<?php
declare(strict_types=1);

final class Database
{
    public static function config(): array
    {
        $file = getenv('CUMPLE_CONFIG') ?: dirname(__DIR__).'/config/local.php';
        if (!is_file($file)) throw new RuntimeException('Instala la base de datos y configura config/local.php. Consulta INSTALACION.md.');
        return require $file;
    }
    public static function connect(array $config, bool $withDatabase = true): PDO
    {
        $dsn = 'mysql:host='.$config['db_host'].';port='.($config['db_port']??3306).';charset=utf8mb4';
        if ($withDatabase) $dsn .= ';dbname='.$config['db_name'];
        $db = new PDO($dsn, $config['db_user'], $config['db_pass'], [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC, PDO::ATTR_EMULATE_PREPARES=>false]);
        $db->exec("SET time_zone='-06:00'");
        return $db;
    }
    public static function transaction(PDO $db, callable $work): mixed
    {
        $db->beginTransaction();
        try { $value=$work(); $db->commit(); return $value; }
        catch(Throwable $e) { if ($db->inTransaction()) $db->rollBack(); throw $e; }
    }
    public static function audit(PDO $db, ?int $actor, string $event, string $entity, int|string|null $id, array $details=[]): void
    {
        $db->prepare('INSERT INTO audit(actor_id,event,entity,entity_id,details,created_at) VALUES(?,?,?,?,?,NOW())')
            ->execute([$actor,$event,$entity,$id,json_encode($details,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR)]);
    }
}
