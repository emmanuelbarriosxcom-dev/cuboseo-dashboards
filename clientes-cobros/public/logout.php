<?php
declare(strict_types=1);
require __DIR__ . '/../private/bootstrap.php';
try {
    headers_private(); require_user();
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); exit; }
    require_csrf($_POST['csrf'] ?? null);
    $_SESSION = [];
    session_destroy();
    setcookie(session_name(), '', ['expires'=>time()-3600, 'path'=>'/', 'secure'=>!config()['allow_http_local'], 'httponly'=>true, 'samesite'=>'Strict']);
    header('Location: login.php', true, 303);
} catch (Throwable $ex) { http_response_code(403); echo 'No se pudo cerrar la sesión. Vuelve a abrir la herramienta.'; }
