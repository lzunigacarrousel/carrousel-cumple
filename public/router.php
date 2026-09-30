<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/app/Http.php';
Http::localOnly();
$path = rawurldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?: '/');
if ($path === '/' || $path === '/index.php') {
    require __DIR__ . '/index.php';
    return true;
}
if ($path === '/api.php') {
    require __DIR__ . '/api.php';
    return true;
}
$file = realpath(__DIR__ . $path);
$publicRoot = realpath(__DIR__) . DIRECTORY_SEPARATOR;
$extension = $file ? strtolower(pathinfo($file, PATHINFO_EXTENSION)) : '';
if ($file && str_starts_with($file, $publicRoot) && is_file($file) && in_array($extension, ['css','js','png','jpg','svg','ico','woff2'], true)) {
    return false;
}
http_response_code(404);
header('Content-Type: text/plain; charset=utf-8');
echo 'Página no encontrada.';
return true;
