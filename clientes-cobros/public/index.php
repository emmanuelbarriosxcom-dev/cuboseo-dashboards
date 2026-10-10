<?php
declare(strict_types=1);
require __DIR__ . '/../private/bootstrap.php';
try {
    headers_private(); $u = require_user();
    $boot = read_state() + ['csrf'=>csrf(), 'username'=>$u['username'], 'serverToday'=>date('Y-m-d')];
    session_write_close();
    $html = file_get_contents(__DIR__.'/../private/dashboard.html');
    $data = json_encode($boot, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    echo str_replace('<!--SERVER_BOOT-->', '<script>window.CUBO_SERVER_BOOT='.$data.';</script>', $html);
} catch (Throwable $ex) {
    http_response_code(503); error_log('Cubo index: '.$ex->getMessage());
    page('Herramienta no disponible', '<p class="error">No se pudieron cargar tus datos. Revisa la instalación o vuelve a iniciar sesión.</p><p><a href="login.php">Volver al acceso</a></p>');
}
