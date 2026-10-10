<?php
declare(strict_types=1);
require __DIR__ . '/../private/bootstrap.php';
try {
    headers_private(); $u = require_user(); $error = ''; $success = false;
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        require_csrf($_POST['csrf'] ?? null);
        $old = (string)($_POST['old'] ?? ''); $new = (string)($_POST['new'] ?? '');
        $q = db()->prepare('SELECT password_hash FROM cubo_users WHERE id = ?'); $q->execute([$u['id']]);
        if (!limit_login($u['username']) || strlen($old)>200 || !password_verify($old, $q->fetchColumn())) $error = 'No se pudo verificar la contraseña actual. Revisa el dato o espera si hiciste varios intentos.';
        elseif (strlen($new)<12 || strlen($new)>72 || $new !== ($_POST['confirm'] ?? '')) $error = 'Usa entre 12 y 72 bytes y escribe la misma contraseña en ambos campos.';
        else {
            $newHash=password_hash($new, PASSWORD_DEFAULT);
            db()->prepare('UPDATE cubo_users SET password_hash = ? WHERE id = ?')->execute([$newHash, $u['id']]);
            $_SESSION['password_stamp']=hash('sha256',$newHash);
            clear_login_limits($u['username']);
            session_regenerate_id(true); $_SESSION['csrf'] = bin2hex(random_bytes(32)); $success = true;
        }
    }
    page('Cambiar contraseña', ($error ? '<p class="error" role="alert">'.e($error).'</p>' : '') . ($success ? '<p class="success">Contraseña actualizada.</p>' : '') . '<form method="post"><input type="hidden" name="csrf" value="'.e(csrf()).'"><label>Contraseña actual<input type="password" name="old" autocomplete="current-password" required maxlength="200"></label><label>Nueva contraseña<input type="password" name="new" autocomplete="new-password" required minlength="12" maxlength="72"></label><label>Repetir nueva contraseña<input type="password" name="confirm" autocomplete="new-password" required minlength="12" maxlength="72"></label><button>Guardar contraseña</button></form><p><a href="./">Volver al dashboard</a></p>');
} catch (Throwable $ex) { http_response_code(503); error_log('Cubo password: '.$ex->getMessage()); page('Acceso no disponible', '<p class="error">Vuelve a iniciar sesión y prueba nuevamente.</p>'); }
