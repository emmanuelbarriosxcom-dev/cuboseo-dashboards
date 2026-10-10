<?php
declare(strict_types=1);
ini_set('display_errors', '0');
ini_set('log_errors', '1');

function config(): array {
    static $c;
    if ($c !== null) return $c;
    $path = __DIR__ . '/config.php';
    if (!is_file($path)) throw new RuntimeException('La instalación necesita su configuración privada.');
    $c = require $path;
    if (!is_array($c)) throw new RuntimeException('Configuración no válida.');
    $origin = parse_url($c['app_origin'] ?? '');
    $local = ($c['allow_http_local'] ?? false) && in_array($origin['host'] ?? '', ['127.0.0.1', 'localhost'], true);
    if (!isset($origin['host']) || (!$local && ($origin['scheme'] ?? '') !== 'https') || isset($origin['path']) && $origin['path'] !== '') throw new RuntimeException('Configura el origen HTTPS del subdominio, sin ruta.');
    date_default_timezone_set($c['timezone'] ?? 'America/Tijuana');
    return $c;
}
function db(): PDO {
    static $pdo;
    if ($pdo) return $pdo;
    $c = config();
    if (!extension_loaded('pdo_mysql')) throw new RuntimeException('PDO MySQL no está disponible en este PHP.');
    $pdo = new PDO('mysql:host=' . $c['db_host'] . ';port=' . (int)($c['db_port'] ?? 3306) . ';dbname=' . $c['db_name'] . ';charset=utf8mb4', $c['db_user'], $c['db_password'], [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION, PDO::ATTR_EMULATE_PREPARES=>false, PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
    $pdo->exec("SET time_zone = '+00:00'");
    return $pdo;
}
function headers_private(): void {
    $c = config();
    if (PHP_SAPI !== 'cli') {
        $secure = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
        $local = ($c['allow_http_local'] ?? false) && in_array($_SERVER['REMOTE_ADDR'] ?? '', ['127.0.0.1', '::1'], true);
        if (!$secure && !$local) {
            header('Location: ' . $c['app_origin'] . '/', true, 303);
            exit;
        }
        // Evitar que páginas privadas se almacenen o se incrusten en otro sitio.
        header('Cache-Control: no-store, private');
        header('X-Content-Type-Options: nosniff');
        header('X-Frame-Options: DENY');
        header('Referrer-Policy: same-origin');
        header('X-Robots-Tag: noindex, nofollow, noarchive');
        header('Permissions-Policy: camera=(), microphone=(), geolocation=()');
        header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline'; style-src 'self' 'unsafe-inline'; img-src 'self' data:; font-src 'self' data:; connect-src 'self'; object-src 'none'; base-uri 'none'; frame-ancestors 'none'; form-action 'self'");
        if ($secure) header('Strict-Transport-Security: max-age=31536000');
    }
}
function session_boot(): void {
    if (session_status() === PHP_SESSION_ACTIVE) return;
    $c = config();
    session_name('CUBOSEOSESSID');
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    ini_set('session.gc_maxlifetime', '28800');
    session_set_cookie_params(['lifetime'=>0, 'path'=>'/', 'secure'=>!($c['allow_http_local'] ?? false), 'httponly'=>true, 'samesite'=>'Strict']);
    session_start();
    if (isset($_SESSION['last_activity']) && time() - $_SESSION['last_activity'] > 28800) {
        $_SESSION = [];
        session_regenerate_id(true);
    }
    $_SESSION['last_activity'] = time();
    $_SESSION['csrf'] ??= bin2hex(random_bytes(32));
}
function csrf(): string { session_boot(); return $_SESSION['csrf']; }
function require_csrf(?string $token): void {
    session_boot();
    $origin = $_SERVER['HTTP_ORIGIN'] ?? null;
    if ($origin !== null && $origin !== config()['app_origin']) throw new DomainException('Origen de solicitud no permitido.');
    if (!$token || !hash_equals($_SESSION['csrf'], $token)) throw new DomainException('La sesión cambió. Vuelve a abrir la herramienta.');
}
function require_user(bool $json = false): array {
    session_boot();
    if (isset($_SESSION['user_id'])) {
        $q = db()->prepare('SELECT id, username, password_hash FROM cubo_users WHERE id = ?');
        $q->execute([$_SESSION['user_id']]);
        if (($u = $q->fetch()) && isset($_SESSION['password_stamp']) && hash_equals($_SESSION['password_stamp'], hash('sha256',$u['password_hash']))) { unset($u['password_hash']); return $u; }
    }
    if ($json) json_response(['error'=>'Inicia sesión nuevamente para continuar.'], 401);
    header('Location: login.php', true, 303);
    exit;
}
function json_response(array $data, int $status = 200): never {
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    exit;
}
function e(string $s): string { return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function empty_state(): array {
    $today = date('Y-m-d');
    return ['version'=>1, 'settings'=>['today'=>$today, 'dateMode'=>'real', 'demo'=>false, 'rates'=>['MXN'=>1, 'USD'=>18, 'EUR'=>20], 'rateDate'=>$today], 'clients'=>[], 'services'=>[], 'charges'=>[], 'payments'=>[], 'history'=>[]];
}
function read_state(): array {
    $row = db()->query('SELECT revision, payload, updated_at FROM cubo_state WHERE id = 1')->fetch();
    if (!$row) throw new RuntimeException('Completa el alta inicial antes de abrir la herramienta.');
    return ['revision'=>(int)$row['revision'], 'state'=>json_decode($row['payload'], true, 512, JSON_THROW_ON_ERROR), 'updatedAt'=>$row['updated_at']];
}
function limit_login(string $username): bool {
    // Cuenta e IP tienen límites independientes: no se elude cambiando de cuenta.
    $buckets = [hash('sha256', 'user:' . strtolower($username)), hash('sha256', 'ip:' . ($_SERVER['REMOTE_ADDR'] ?? ''))];
    $pdo = db(); $now = time(); $blocked = false;
    foreach ($buckets as $bucket) {
        $q = $pdo->prepare('INSERT INTO cubo_login_limits (bucket, attempts, window_start) VALUES (?, 1, ?) ON DUPLICATE KEY UPDATE attempts = IF(window_start < ?, 1, LEAST(attempts + 1, 1000)), window_start = IF(window_start < ?, VALUES(window_start), window_start)');
        $q->execute([$bucket, $now, $now-900, $now-900]);
        $q = $pdo->prepare('SELECT attempts FROM cubo_login_limits WHERE bucket = ?');
        $q->execute([$bucket]);
        if ((int)$q->fetchColumn() > 10) $blocked = true;
    }
    $pdo->prepare('DELETE FROM cubo_login_limits WHERE window_start < ?')->execute([$now-86400]);
    return !$blocked;
}
function page(string $title, string $content): void {
    echo '<!doctype html><html lang="es"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex,nofollow"><title>' . e($title) . ' · Cubo SEO</title><link rel="stylesheet" href="auth.css"><body><main class="auth-card"><a class="brand" href="./" aria-label="Cubo SEO">' . file_get_contents(__DIR__.'/logo.svg') . '</a><p class="kicker">CLIENTES Y COBROS</p><h1>' . e($title) . '</h1>' . $content . '</main></body></html>';
}
function clear_login_limits(string $username): void {
    $q=db()->prepare('DELETE FROM cubo_login_limits WHERE bucket IN (?,?)');
    $q->execute([hash('sha256','user:'.strtolower($username)),hash('sha256','ip:'.($_SERVER['REMOTE_ADDR']??''))]);
}
