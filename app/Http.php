<?php
declare(strict_types=1);

final class Http
{
    public static function localOnly(): void
    {
        $address = $_SERVER['REMOTE_ADDR'] ?? '';
        $host = strtolower((string)($_SERVER['HTTP_HOST'] ?? ''));
        $port = (int)($_SERVER['SERVER_PORT'] ?? 0);
        $allowedHosts = ['localhost', '127.0.0.1', '[::1]'];
        if ($port !== 80) {
            $allowedHosts = array_map(fn($item) => $item . ':' . $port, $allowedHosts);
        }
        if (!in_array($address, ['127.0.0.1', '::1'], true) || !in_array($host, $allowedHosts, true)) {
            self::fail('Esta demostración solo admite acceso desde este equipo.', 403);
        }
        $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
        if ($origin !== '' && $origin !== 'http://' . $host) {
            self::fail('Origen de solicitud no permitido.', 403);
        }
        header('Cache-Control: no-store');
        header('X-Content-Type-Options: nosniff');
        header('Referrer-Policy: no-referrer');
        header("Content-Security-Policy: default-src 'self'; script-src 'self'; style-src 'self' 'unsafe-inline'; img-src 'self' data:; connect-src 'self'; font-src 'self'; object-src 'none'; base-uri 'self'; form-action 'self'; frame-ancestors 'none'");
        header('Permissions-Policy: camera=(self), microphone=(), geolocation=()');
    }

    public static function success(mixed $data): never
    {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok' => true, 'data' => $data], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        exit;
    }

    public static function fail(string $message, int $status): never
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok' => false, 'error' => $message], JSON_UNESCAPED_UNICODE);
        exit;
    }

    public static function input(): array
    {
        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
            self::fail('Método no permitido.', 405);
        }
        if (!hash_equals($_SESSION['csrf'], $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '')) {
            self::fail('La sesión del formulario cambió. Recarga e inténtalo de nuevo.', 403);
        }
        if (!str_starts_with(strtolower($_SERVER['CONTENT_TYPE'] ?? ''), 'application/json')) {
            self::fail('Se requiere contenido JSON.', 415);
        }
        $raw = file_get_contents('php://input', false, null, 0, 32769);
        if ($raw === false || strlen($raw) > 32768) {
            self::fail('La solicitud es demasiado grande.', 413);
        }
        try {
            $result = json_decode($raw, true, 32, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            self::fail('Solicitud no válida.', 400);
        }
        if (!is_array($result)) {
            self::fail('Solicitud no válida.', 400);
        }
        foreach ($result as $value) {
            if (is_array($value) || is_object($value)) {
                self::fail('Los campos deben contener valores simples.', 400);
            }
        }
        return $result;
    }

    public static function user(PDO $db): ?array
    {
        if (!isset($_SESSION['user_id'])) {
            return null;
        }
        return (new Accounts($db))->user((int)$_SESSION['user_id']);
    }

    public static function requireUser(PDO $db, bool $admin = false): array
    {
        $user = self::user($db);
        if (!$user) {
            self::fail('Inicia sesión para acceder al panel.', 401);
        }
        if ($admin && $user['role'] !== 'admin') {
            self::fail('Esta acción requiere una cuenta administradora.', 403);
        }
        return $user;
    }

    public static function throttle(PDO $db, string $action, int $limit): void
    {
        $key = hash('sha256', ($_SERVER['REMOTE_ADDR'] ?? '') . ':' . $action);
        $bucket = intdiv(time(), 60);
        $stmt = $db->prepare('INSERT INTO request_limits (limit_key,bucket,hits) VALUES (?,?,1) ON CONFLICT(limit_key,bucket) DO UPDATE SET hits=hits+1');
        $stmt->execute([$key, $bucket]);
        $stmt = $db->prepare('SELECT hits FROM request_limits WHERE limit_key=? AND bucket=?');
        $stmt->execute([$key, $bucket]);
        if ((int)$stmt->fetchColumn() > $limit) {
            header('Retry-After: 60');
            self::fail('Demasiados intentos. Espera un minuto y vuelve a intentar.', 429);
        }
        $stmt = $db->prepare('DELETE FROM request_limits WHERE bucket < ?');
        $stmt->execute([$bucket - 60]);
    }

    public static function operationPark(PDO $db, array $user, array $input): int
    {
        try {
            return (new Accounts($db))->operationPark($user, $input);
        } catch (DomainException $error) {
            self::fail($error->getMessage(), 403);
        }
    }

    public static function staffCoupon(array $coupon, array $user): array
    {
        unset($coupon['token']);
        // Redeeming a gift does not grant access to another park's contact records.
        if ($user['role'] !== 'admin' && $coupon['park_id'] !== $user['park_id']) {
            unset($coupon['phone'], $coupon['email'], $coupon['guardian']);
        }
        return $coupon;
    }

    public static function publicCoupon(array $coupon): array
    {
        return array_intersect_key($coupon, array_flip([
            'code','name','park_name','benefit_label','terms','valid_from','valid_until','status',
        ]));
    }

    public static function query(string $key, int $max = 200): string
    {
        $value = $_GET[$key] ?? '';
        if (!is_string($value) || mb_strlen($value) > $max) {
            self::fail('Parámetro de búsqueda no válido.', 400);
        }
        return trim($value);
    }

    public static function csvCell(mixed $value): string
    {
        $value = (string)$value;
        return preg_match('/^[\s]*[=+@\-\t\r\n]/u', $value) ? "'" . $value : $value;
    }
}
