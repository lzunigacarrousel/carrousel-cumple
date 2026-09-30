<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$root = dirname(__DIR__);
require_once $root.'/app/Database.php';

function validateTestConfig(array $config): void
{
    if (($config['db_name'] ?? '') !== 'carrousel_cumple_test') {
        throw new RuntimeException('Solo se permite la base carrousel_cumple_test.');
    }
    if (!in_array($config['db_host'] ?? '', ['127.0.0.1', 'localhost', '::1'], true)) {
        throw new RuntimeException('La base de pruebas debe estar en localhost o en un contenedor publicado en loopback.');
    }
    if (($config['db_port'] ?? 0) < 1 || $config['db_port'] > 65535 || empty($config['db_user'])) {
        throw new RuntimeException('Usuario o puerto de pruebas inválido.');
    }
    if (!empty($config['automation_enabled']) || !empty($config['smtp_username']) || !empty($config['smtp_password'])
        || !in_array($config['smtp_host'] ?? '', ['', '127.0.0.1', 'localhost', '::1'], true)) {
        throw new RuntimeException('Pruebas requiere automatización apagada y SMTP sin credenciales ni servidor externo.');
    }
}

try {
    $args = array_slice($argv, 1);
    if (array_diff($args, ['--check'])) {
        throw new RuntimeException('Uso: php bin/setup-tests.php [--check]');
    }
    // Explicitly reject an unsafe environment value even when a prior config exists.
    $requestedName = getenv('CUMPLE_TEST_DB_NAME');
    if ($requestedName !== false && $requestedName !== 'carrousel_cumple_test') {
        throw new RuntimeException('CUMPLE_TEST_DB_NAME debe ser carrousel_cumple_test.');
    }
    $file = $root.'/config/test.php';
    $exists = is_file($file);
    $config = require ($exists ? $file : $root.'/config/test.php.example');
    if (!is_array($config)) {
        throw new RuntimeException('La configuración de pruebas debe devolver un arreglo.');
    }
    validateTestConfig($config);
    if (in_array('--check', $args, true)) {
        echo "Configuración de pruebas válida; no se modificó ningún archivo ni base.\n";
        exit;
    }
    if (PHP_VERSION_ID < 80200) {
        throw new RuntimeException('Se requiere PHP 8.2 o posterior.');
    }
    foreach (['pdo_mysql', 'mbstring', 'openssl'] as $extension) {
        if (!extension_loaded($extension)) {
            throw new RuntimeException('Falta la extensión PHP: '.$extension);
        }
    }
    if (!$exists) {
        $config['app_key'] = bin2hex(random_bytes(32));
    }
    if (strlen((string) ($config['app_key'] ?? '')) < 32 || $config['app_key'] === 'GENERAR_CON_SETUP_TESTS') {
        throw new RuntimeException('Configura una app_key aleatoria de al menos 32 caracteres en config/test.php.');
    }
    try {
        $db = Database::connect($config, false);
        $db->exec('CREATE DATABASE IF NOT EXISTS `carrousel_cumple_test` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
        $db->exec('USE `carrousel_cumple_test`');
    } catch (PDOException) {
        throw new RuntimeException('No se pudo preparar MariaDB local. Revisa CUMPLE_TEST_DB_* y concede permisos solo sobre carrousel_cumple_test. No se imprimen credenciales.');
    }
    if ($db->query('SELECT DATABASE()')->fetchColumn() !== 'carrousel_cumple_test') {
        throw new RuntimeException('Conexión fuera de la base aislada; operación cancelada.');
    }
    $db->exec(file_get_contents($root.'/database/schema.sql'));
    if (!$exists) {
        // Exclusive creation prevents accidental replacement of a collaborator's file.
        $handle = fopen($file, 'x');
        if (!$handle) {
            throw new RuntimeException('La configuración apareció durante la instalación; no se sobrescribió.');
        }
        try {
            if (fwrite($handle, "<?php\nreturn ".var_export($config, true).";\n") === false) {
                throw new RuntimeException('No se pudo guardar la configuración de pruebas.');
            }
        } finally {
            fclose($handle);
        }
        @chmod($file, 0600);
    }
    if (!is_dir($root.'/storage') && !mkdir($root.'/storage', 0700, true)) {
        throw new RuntimeException('No se pudo crear storage para las pruebas.');
    }
    echo "Esquema carrousel_cumple_test preparado; config/test.php ".($exists ? 'conservada' : 'creada').".\n";
    echo "No se cargaron datos reales ni se enviaron correos. production.php reiniciará únicamente los fixtures de esta base.\n";
} catch (Throwable $error) {
    fwrite(STDERR, $error->getMessage()."\n");
    exit(1);
}
