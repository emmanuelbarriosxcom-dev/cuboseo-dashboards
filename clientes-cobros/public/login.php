<?php
declare(strict_types=1);
require __DIR__ . '/../private/bootstrap.php';
try {
    headers_private(); session_boot();
    $error = '';
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        require_csrf($_POST['csrf'] ?? null);
        $name = trim((string)($_POST['username'] ?? ''));
        $pass = (string)($_POST['password'] ?? '');
        if (strlen($name) > 80 || strlen($pass) > 200 || !limit_login($name)) {
            $error = 'Demasiados intentos. Espera 15 minutos antes de volver a entrar.';
        } else {
            $q = db()->prepare('SELECT id, password_hash FROM cubo_users WHERE username = ?');
            $q->execute([$name]); $u = $q->fetch();
            $hash = $u['password_hash'] ?? '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2uheWG/igi';
            $valid = password_verify($pass, $hash);
            if ($u && $valid) {
                clear_login_limits($name);
                session_regenerate_id(true);
                $_SESSION['user_id'] = (int)$u['id'];
                $_SESSION['password_stamp'] = hash('sha256',$u['password_hash']);
                $_SESSION['csrf'] = bin2hex(random_bytes(32));
                $_SESSION['last_activity'] = time();
                header('Location: ./', true, 303); exit;
            }
            $error = 'El usuario o la contraseña no son correctos.';
        }
    }
    page('Acceso privado', ($error ? '<p class="error" role="alert">'.e($error).'</p>' : '') . '<form method="post"><input type="hidden" name="csrf" value="'.e(csrf()).'"><label>Usuario<input name="username" autocomplete="username" required maxlength="80" autocapitalize="none" spellcheck="false"></label><label>Contraseña<input name="password" type="password" autocomplete="current-password" required maxlength="200"></label><button>Entrar</button></form><p class="muted">Tu información se guarda en el servidor y está disponible desde tus dispositivos.</p>');
} catch (Throwable $ex) {
    http_response_code(503); error_log('Cubo login: '.$ex->getMessage());
    page('Acceso no disponible', '<p class="error">Revisa la configuración y completa la instalación inicial.</p>');
}
