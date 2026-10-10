<?php
declare(strict_types=1);
require __DIR__ . '/../private/bootstrap.php';
try {
    headers_private(); session_boot(); $pdo = db(); $error = '';
    try { $installed = (int)$pdo->query('SELECT COUNT(*) FROM cubo_users')->fetchColumn() > 0; }
    catch (PDOException $ex) { if (($ex->errorInfo[1] ?? 0) !== 1146) throw $ex; $installed = false; }
    if ($installed) { http_response_code(410); page('Alta inicial cerrada', '<p>La cuenta inicial ya existe.</p><p><a href="login.php">Entrar</a></p>'); exit; }
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        require_csrf($_POST['csrf'] ?? null);
        $key = config()['setup_key'] ?? '';
        $name = trim((string)($_POST['username'] ?? '')); $pass = (string)($_POST['password'] ?? '');
        if (strlen($key)<32 || $key==='GENERAR_CLAVE_ALEATORIA_EN_SERVIDOR' || !hash_equals($key, (string)($_POST['setup_key'] ?? ''))) $error = 'La clave de instalación no es válida.';
        elseif (!preg_match('/^[a-zA-Z0-9_.-]{3,80}$/D', $name)) $error = 'Usa un usuario de 3 a 80 caracteres: letras, números, punto, guion o guion bajo.';
        elseif (strlen($pass)<12 || strlen($pass)>72 || $pass !== ($_POST['confirm'] ?? '')) $error = 'Usa una contraseña de entre 12 y 72 bytes y repítela exactamente.';
        else {
            foreach (explode(';', file_get_contents(__DIR__.'/../private/schema.sql')) as $sql) if (trim($sql)) $pdo->exec($sql);
            $pdo->beginTransaction();
            // INSERT único bloquea una segunda instalación concurrente; no reemplaza datos.
            $pdo->prepare('INSERT INTO cubo_state (id, revision, payload, updated_at) VALUES (1, 0, ?, UTC_TIMESTAMP())')->execute([json_encode(empty_state(), JSON_THROW_ON_ERROR)]);
            $pdo->prepare('INSERT INTO cubo_users (username, password_hash, created_at) VALUES (?, ?, UTC_TIMESTAMP())')->execute([$name, password_hash($pass, PASSWORD_DEFAULT)]);
            $pdo->commit();
            page('Instalación inicial completada', '<p class="success">La cuenta y la base de datos están listas. No se cargaron ejemplos ni clientes.</p><p><a href="login.php">Entrar a la herramienta</a></p>'); exit;
        }
    }
    page('Alta inicial', '<p class="muted">PDO MySQL y la conexión a la base de datos funcionan. Completa el acceso privado.</p>'.($error?'<p class="error" role="alert">'.e($error).'</p>':'').'<form method="post"><input type="hidden" name="csrf" value="'.e(csrf()).'"><label>Clave de instalación<input name="setup_key" type="password" required autocomplete="off"></label><label>Usuario<input name="username" required minlength="3" maxlength="80" autocomplete="username" autocapitalize="none"></label><label>Contraseña<input name="password" type="password" required minlength="12" maxlength="72" autocomplete="new-password"></label><label>Repetir contraseña<input name="confirm" type="password" required minlength="12" maxlength="72" autocomplete="new-password"></label><button>Crear acceso privado</button></form>');
} catch (Throwable $ex) {
    if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
    http_response_code(503); error_log('Cubo setup: '.$ex->getMessage());
    page('Instalación pendiente', '<p class="error">La conexión no está lista o el alta inicial no pudo completarse. Revisa PHP, PDO MySQL y la configuración privada.</p>');
}
